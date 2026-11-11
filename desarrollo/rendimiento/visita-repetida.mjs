/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

// Abre una pantalla dos veces con la red y la CPU limitadas de verdad, como un teléfono con mala red, y compara la primera
// visita con la segunda, que es la de todos los días y Lighthouse no mide (docs/rendimiento-movil.md).
// No se instala ni va en los paquetes, y no necesita dependencias: habla con Chrome por su protocolo de depuración.
//
//   node desarrollo/rendimiento/visita-repetida.mjs --base=http://127.0.0.1:8014 --cookie=cookie.txt --rutas=/hoy,/calendario
//
// Con --recorrido=/hoy,/calendario,/reservas,atras, ya con todo en caché, pulsa el enlace a cada pantalla y mide el cambio, tanto si
// carga la página entera como si Livewire solo cambia el cuerpo (modo SPA). Con --sin-recargar, no vuelve a cargar la página entera
// entre una vuelta y otra, y dice cuánta memoria va usando. Con --red-rapida limita solo la CPU.

import { spawn } from 'node:child_process';
import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const opciones = Object.fromEntries(process.argv.slice(2).map((a) => {
    const [clave, ...valor] = a.replace(/^--/, '').split('=');

    return [clave, valor.length ? valor.join('=') : true];
}));

const base = String(opciones.base ?? 'http://127.0.0.1:8014').replace(/\/$/, '');
const rutas = String(opciones.rutas ?? '/hoy').split(',');
const veces = Number(opciones.veces ?? 3);
const recorrido = opciones.recorrido ? String(opciones.recorrido).split(',') : null;
const cookie = opciones.cookie ? readFileSync(opciones.cookie, 'utf8').trim() : null;
const chrome = process.env.CHROME_PATH ?? 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const puerto = Number(opciones.puerto ?? 9333);

// Los de Lighthouse móvil cuando limita de verdad (mobileSlow4G): los 150 ms de red son 562.5 por petición, porque cuenta
// también la conexión, y 1.6 Mbps son 1.47 útiles.
const RED = { offline: false, latency: 562.5, downloadThroughput: (1474.56 * 1024) / 8, uploadThroughput: (675 * 1024) / 8 };
const CPU = 4;

const esperar = (ms) => new Promise((listo) => setTimeout(listo, ms));

class Pestana {
    constructor(socket) {
        this.socket = socket;
        this.siguiente = 1;
        this.pendientes = new Map();
        this.oyentes = new Set();
        socket.addEventListener('message', (evento) => {
            const mensaje = JSON.parse(evento.data);
            if (mensaje.id && this.pendientes.has(mensaje.id)) {
                this.pendientes.get(mensaje.id)(mensaje.result ?? {});
                this.pendientes.delete(mensaje.id);
            } else if (mensaje.method) {
                this.oyentes.forEach((oyente) => oyente(mensaje));
            }
        });
    }

    enviar(metodo, parametros = {}) {
        const id = this.siguiente++;

        return new Promise((listo) => {
            this.pendientes.set(id, listo);
            this.socket.send(JSON.stringify({ id, method: metodo, params: parametros }));
        });
    }

    // Una visita: bytes y peticiones por la red, y lo que dice el navegador sobre lo que se pintó.
    async visitar(url) {
        const peticiones = new Map();
        const oyente = ({ method, params }) => {
            if (method === 'Network.requestWillBeSent' && !params.request.url.startsWith('data:')) {
                peticiones.set(params.requestId, { red: true, bytes: 0, estado: 0 });
            } else if (method === 'Network.requestServedFromCache' && peticiones.has(params.requestId)) {
                peticiones.get(params.requestId).red = false;
            } else if (method === 'Network.responseReceived' && peticiones.has(params.requestId)) {
                peticiones.get(params.requestId).estado = params.response.status;
                if (params.response.fromDiskCache || params.response.fromPrefetchCache) {
                    peticiones.get(params.requestId).red = false;
                }
            } else if (method === 'Network.loadingFinished' && peticiones.has(params.requestId)) {
                peticiones.get(params.requestId).bytes = params.encodedDataLength;
            }
        };
        this.oyentes.add(oyente);
        const cargada = new Promise((listo) => {
            const alCargar = ({ method }) => {
                if (method === 'Page.loadEventFired') {
                    this.oyentes.delete(alCargar);
                    listo();
                }
            };
            this.oyentes.add(alCargar);
        });
        await this.enviar('Page.navigate', { url });
        await Promise.race([cargada, esperar(90000)]);
        await esperar(4000);
        this.oyentes.delete(oyente);
        const { result } = await this.enviar('Runtime.evaluate', { returnByValue: true, expression: 'JSON.stringify(window.__medidas ?? {})' });
        const medidas = JSON.parse(result.value);
        const porRed = [...peticiones.values()].filter((p) => p.red);

        return {
            ...medidas,
            final: (await this.enviar('Runtime.evaluate', { returnByValue: true, expression: 'location.pathname' })).result.value,
            kb: porRed.reduce((suma, p) => suma + p.bytes, 0) / 1024,
            porRed: porRed.length,
            comprobadas: porRed.filter((p) => p.estado === 304).length,
            total: peticiones.size,
        };
    }

    async evaluar(expresion) {
        const { result } = await this.enviar('Runtime.evaluate', { returnByValue: true, expression: expresion });

        return result?.value;
    }

    // Pulsa el enlace a otra pantalla (o vuelve atrás) y espera a que todo responda.
    async cambiarA(destino) {
        const peticiones = [];
        const oyente = ({ method, params }) => {
            if (method === 'Network.loadingFinished' && params.encodedDataLength > 0) {
                peticiones.push(params.encodedDataLength);
            }
        };
        this.oyentes.add(oyente);
        const accion = destino === 'atras' ? 'history.back()' : `document.querySelector('a[href$="${destino}"]').click()`;
        const t0 = await this.evaluar(`(() => { const t0 = Date.now(); sessionStorage.setItem('__t0', t0); window.__reiniciarCambio(t0); ${accion}; return t0; })()`);
        let cambio = null;
        for (let i = 0; i < 300 && cambio === null; i++) {
            await esperar(100);
            const leido = JSON.parse(await this.evaluar('JSON.stringify(window.__cambio ?? null)') ?? 'null');
            cambio = leido !== null && leido.t0 === t0 && (leido.alpine || leido.navegado) ? leido : null;
        }
        if (cambio === null) {
            throw new Error(`${destino}: no terminó de cambiar: ${await this.evaluar('location.href')}`);
        }
        // Lo último en llegar: el pintado y las tareas largas.
        await esperar(1500);
        cambio = JSON.parse(await this.evaluar('JSON.stringify(window.__cambio)'));
        this.oyentes.delete(oyente);

        return {
            contenido: (cambio.alpine ? cambio.lcp : cambio.pintado) - t0,
            responde: Math.max(cambio.alpine ?? 0, cambio.navegado ?? 0) - t0,
            bloqueo: cambio.largas.reduce((suma, [inicio, duracion]) => suma + (inicio >= t0 ? Math.max(0, duracion - 50) : 0), 0),
            kb: peticiones.reduce((suma, bytes) => suma + bytes, 0) / 1024,
            peticiones: peticiones.length,
            final: await this.evaluar('location.pathname'),
        };
    }

    async memoria() {
        await this.enviar('HeapProfiler.collectGarbage');
        const { usedSize } = await this.enviar('Runtime.getHeapUsage');

        return usedSize / 1048576;
    }
}

const mediana = (valores) => [...valores].sort((a, b) => a - b)[Math.floor(valores.length / 2)];

async function abrirPestana() {
    const respuesta = await fetch(`http://127.0.0.1:${puerto}/json/new?about:blank`, { method: 'PUT' });
    const { webSocketDebuggerUrl } = await respuesta.json();
    const socket = new WebSocket(webSocketDebuggerUrl);
    await new Promise((listo, fallo) => {
        socket.addEventListener('open', listo);
        socket.addEventListener('error', fallo);
    });
    const pestana = new Pestana(socket);
    await pestana.enviar('Page.enable');
    await pestana.enviar('Network.enable');
    await pestana.enviar('Runtime.enable');
    await pestana.enviar('Emulation.setDeviceMetricsOverride', { width: 412, height: 823, deviceScaleFactor: 1.75, mobile: true });
    await pestana.enviar('Emulation.setCPUThrottlingRate', { rate: CPU });
    if (!opciones['red-rapida']) {
        await pestana.enviar('Network.emulateNetworkConditions', RED);
    }
    if (cookie) {
        await pestana.enviar('Network.setExtraHTTPHeaders', { headers: { Cookie: cookie } });
    }
    await pestana.enviar('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__medidas = {};
            new PerformanceObserver((lista) => { for (const e of lista.getEntries()) { if (e.name === 'first-contentful-paint') { window.__medidas.fcp = e.startTime; } } }).observe({ type: 'paint', buffered: true });
            new PerformanceObserver((lista) => { const e = lista.getEntries().at(-1); window.__medidas.lcp = e.startTime; window.__medidas.elemento = e.element?.tagName; }).observe({ type: 'largest-contentful-paint', buffered: true });
            new PerformanceObserver((lista) => { for (const e of lista.getEntries()) { if (!e.hadRecentInput) { window.__medidas.cls = (window.__medidas.cls ?? 0) + e.value; } } }).observe({ type: 'layout-shift', buffered: true });
            document.addEventListener('alpine:initialized', () => { window.__medidas.alpine = performance.now(); });

            const ahora = () => performance.timeOrigin + performance.now();
            window.__cambio = { t0: Number(sessionStorage.getItem('__t0') ?? 0), largas: [] };
            window.__reiniciarCambio = (t0) => { window.__cambio = { t0, largas: [] }; };
            new PerformanceObserver((lista) => { window.__cambio.lcp = performance.timeOrigin + lista.getEntries().at(-1).startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
            new PerformanceObserver((lista) => { for (const e of lista.getEntries()) { window.__cambio.largas.push([performance.timeOrigin + e.startTime, e.duration]); } }).observe({ type: 'longtask', buffered: true });
            document.addEventListener('alpine:initialized', () => { window.__cambio.alpine = ahora(); });
            // Sin página nueva no hay LCP. Livewire cambia el cuerpo y arranca Alpine sin dejar pintar en medio, así que lo más
            // cercano es el segundo fotograma después de terminar.
            document.addEventListener('livewire:navigated', () => {
                const cambio = window.__cambio;
                cambio.navegado = ahora();
                requestAnimationFrame(() => requestAnimationFrame(() => { cambio.pintado = ahora(); }));
            });`,
    });

    return pestana;
}

const perfil = mkdtempSync(join(tmpdir(), 'alojaflow-medir-'));
const navegador = spawn(chrome, [`--remote-debugging-port=${puerto}`, `--user-data-dir=${perfil}`, '--headless=new', '--no-sandbox', '--no-first-run', '--disable-extensions', 'about:blank'], { stdio: 'ignore' });

try {
    for (let i = 0; i < 40; i++) {
        try {
            await fetch(`http://127.0.0.1:${puerto}/json/version`);
            break;
        } catch {
            await esperar(250);
        }
    }

    if (recorrido !== null) {
        const pestana = await abrirPestana();
        const paginas = [...new Set(recorrido.filter((destino) => destino !== 'atras'))];
        for (let i = 0; i < 2; i++) {
            for (const pagina of paginas) {
                await pestana.visitar(base + pagina);
            }
        }
        const saltos = new Map();
        for (let i = 0; i < veces; i++) {
            if (i === 0 || !opciones['sin-recargar']) {
                await pestana.visitar(base + recorrido[0]);
            }
            let origen = recorrido[0];
            for (const destino of recorrido.slice(1)) {
                const clave = `${origen} → ${destino}`;
                saltos.set(clave, [...(saltos.get(clave) ?? []), await pestana.cambiarA(destino)]);
                origen = destino === 'atras' ? '(atrás)' : destino;
            }
            if (opciones['sin-recargar']) {
                console.log(`vuelta ${i + 1}: ${(await pestana.memoria()).toFixed(1)} MB de memoria de JavaScript, ${await pestana.evaluar('document.querySelectorAll("*").length')} elementos`);
            }
        }
        for (const [clave, medidas] of saltos) {
            const m = (dato) => mediana(medidas.map((c) => c[dato]));
            console.log(`${clave}: contenido ${(m('contenido') / 1000).toFixed(2)} s · responde ${(m('responde') / 1000).toFixed(2)} s · bloqueo ${Math.round(m('bloqueo'))} ms · ${Math.round(m('kb'))} KB en ${m('peticiones')} peticiones`);
        }
        pestana.socket.close();
    }

    for (const ruta of recorrido === null ? rutas : []) {
        const primeras = [];
        const segundas = [];
        for (let i = 0; i < veces; i++) {
            const pestana = await abrirPestana();
            await pestana.enviar('Network.clearBrowserCache');
            primeras.push(await pestana.visitar(base + ruta));
            segundas.push(await pestana.visitar(base + ruta));
            pestana.socket.close();
        }
        for (const [nombre, visitas] of [['primera', primeras], ['segunda', segundas]]) {
            const m = (clave) => mediana(visitas.map((v) => v[clave] ?? 0));
            if (visitas.some((v) => !v.final.endsWith(ruta))) {
                console.error(`${ruta}: terminó en ${visitas.map((v) => v.final).join(', ')}; ¿venció la sesión?`);
            }
            console.log(`${ruta} ${nombre}: FCP ${(m('fcp') / 1000).toFixed(1)} s · LCP ${(m('lcp') / 1000).toFixed(1)} s (${visitas[0].elemento}) · contenido listo con Alpine ${(m('alpine') / 1000).toFixed(1)} s`
                + ` · CLS ${m('cls').toFixed(3)} · ${Math.round(m('kb'))} KB · ${m('porRed')} de ${m('total')} peticiones por la red, ${m('comprobadas')} solo para comprobar`);
        }
    }
} finally {
    navegador.kill();
    await esperar(500);
    rmSync(perfil, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
}
