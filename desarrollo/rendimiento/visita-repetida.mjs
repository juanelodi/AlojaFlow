/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Abre una pantalla dos veces con la red y la CPU limitadas de verdad, como un teléfono con mala red, y compara la primera
// visita con la segunda, que es la de todos los días y Lighthouse no mide (docs/rendimiento-movil.md).
// No se instala ni va en los paquetes, y no necesita dependencias: habla con Chrome por su protocolo de depuración.
//
//   node desarrollo/rendimiento/visita-repetida.mjs --base=http://127.0.0.1:8014 --cookie=cookie.txt --rutas=/hoy,/calendario

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
    await pestana.enviar('Network.emulateNetworkConditions', RED);
    if (cookie) {
        await pestana.enviar('Network.setExtraHTTPHeaders', { headers: { Cookie: cookie } });
    }
    await pestana.enviar('Page.addScriptToEvaluateOnNewDocument', {
        source: `window.__medidas = {};
            new PerformanceObserver((lista) => { for (const e of lista.getEntries()) { if (e.name === 'first-contentful-paint') { window.__medidas.fcp = e.startTime; } } }).observe({ type: 'paint', buffered: true });
            new PerformanceObserver((lista) => { const e = lista.getEntries().at(-1); window.__medidas.lcp = e.startTime; window.__medidas.elemento = e.element?.tagName; }).observe({ type: 'largest-contentful-paint', buffered: true });
            new PerformanceObserver((lista) => { for (const e of lista.getEntries()) { if (!e.hadRecentInput) { window.__medidas.cls = (window.__medidas.cls ?? 0) + e.value; } } }).observe({ type: 'layout-shift', buffered: true });
            document.addEventListener('alpine:initialized', () => { window.__medidas.alpine = performance.now(); });`,
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

    for (const ruta of rutas) {
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
