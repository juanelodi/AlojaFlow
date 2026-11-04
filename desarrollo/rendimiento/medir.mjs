/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

// Mide pantallas del panel con Lighthouse varias veces y resume la mediana, para comparar antes y después
// (docs/rendimiento-movil.md). No se instala ni va en los paquetes. Lighthouse se indica aparte para no añadir dependencias:
//
//   LIGHTHOUSE_CLI=ruta/a/lighthouse/cli/index.js node desarrollo/rendimiento/medir.mjs \
//     --base=http://127.0.0.1:8014 --cookie=cookie.txt --rutas=/hoy,/calendario --veces=5 --salida=medidas/antes
//
// Opciones: --escritorio (por omisión, móvil), --aplicada (limita de verdad la red y la CPU en lugar de simularlo).

import { spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const opciones = Object.fromEntries(process.argv.slice(2).map((a) => {
    const [clave, ...valor] = a.replace(/^--/, '').split('=');

    return [clave, valor.length ? valor.join('=') : true];
}));

const cli = process.env.LIGHTHOUSE_CLI;
if (!cli || !opciones.base || !opciones.salida) {
    console.error('Faltan LIGHTHOUSE_CLI, --base o --salida.');
    process.exit(1);
}

const base = opciones.base.replace(/\/$/, '');
const rutas = String(opciones.rutas ?? '/hoy').split(',');
const veces = Number(opciones.veces ?? 5);
const salida = opciones.salida;
mkdirSync(salida, { recursive: true });

const cabeceras = join(salida, 'cabeceras.json');
writeFileSync(cabeceras, JSON.stringify(opciones.cookie ? { Cookie: readFileSync(opciones.cookie, 'utf8').trim() } : {}));

const mediana = (valores) => {
    const orden = [...valores].sort((a, b) => a - b);

    return orden[Math.floor(orden.length / 2)];
};

const resumen = {};
for (const ruta of rutas) {
    const nombre = ruta.replace(/\W+/g, '-').replace(/^-|-$/g, '') || 'raiz';
    const corridas = [];
    for (let i = 1; i <= veces; i++) {
        const archivo = join(salida, `${nombre}-${i}.json`);
        const argumentos = [
            cli, base + ruta, '--quiet', '--output=json', `--output-path=${archivo}`, '--only-categories=performance',
            `--extra-headers=${cabeceras}`, '--chrome-flags=--headless=new --no-sandbox',
        ];
        if (opciones.escritorio) {
            argumentos.push('--preset=desktop');
        }
        if (opciones.aplicada) {
            argumentos.push('--throttling-method=devtools');
        }
        const proceso = spawnSync(process.execPath, argumentos, { encoding: 'utf8', timeout: 240000 });
        if (proceso.status !== 0) {
            console.error(`${ruta} #${i}: ${proceso.stderr}`);
            continue;
        }
        const informe = JSON.parse(readFileSync(archivo, 'utf8'));
        const auditoria = (id) => informe.audits[id]?.numericValue ?? 0;
        const final = informe.finalDisplayedUrl ?? informe.finalUrl;
        if (!final.endsWith(ruta)) {
            console.error(`${ruta} #${i}: terminó en ${final}; ¿venció la sesión?`);
        }
        corridas.push({
            puntos: Math.round((informe.categories.performance.score ?? 0) * 100),
            fcp: auditoria('first-contentful-paint'),
            lcp: auditoria('largest-contentful-paint'),
            si: auditoria('speed-index'),
            tbt: auditoria('total-blocking-time'),
            cls: auditoria('cumulative-layout-shift'),
            kb: auditoria('total-byte-weight') / 1024,
            hilo: auditoria('mainthread-work-breakdown'),
            js: auditoria('bootup-time'),
        });
    }
    if (corridas.length === 0) {
        continue;
    }
    const fila = Object.fromEntries(Object.keys(corridas[0]).map((m) => [m, mediana(corridas.map((c) => c[m]))]));
    resumen[ruta] = { ...fila, corridas: corridas.map((c) => c.puntos) };
    console.log(`${ruta}: ${fila.puntos} puntos (${corridas.map((c) => c.puntos).join(' ')}) · FCP ${(fila.fcp / 1000).toFixed(1)} s · LCP ${(fila.lcp / 1000).toFixed(1)} s`
        + ` · SI ${(fila.si / 1000).toFixed(1)} s · TBT ${Math.round(fila.tbt)} ms · CLS ${fila.cls.toFixed(3)} · ${Math.round(fila.kb)} KB · hilo ${(fila.hilo / 1000).toFixed(1)} s · JS ${(fila.js / 1000).toFixed(1)} s`);
}
writeFileSync(join(salida, 'resumen.json'), JSON.stringify(resumen, null, 2));
