/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Lo que mejora la pantalla sin hacer falta: sin este archivo todo sigue funcionando, y el servidor
 * valida igual. Se carga en el panel y en las páginas públicas; no usa Livewire ni Alpine a propósito.
 */
(() => {
    'use strict';

    if (window.alojaFlowListo) {
        return;
    }
    window.alojaFlowListo = true;

    /*
     * Rangos de fechas. El campo inicial lleva data-af-rango-inicio="nombre" y el final data-af-rango-fin="nombre".
     *
     * - data-af-rango-dias: cuántos días después de la inicial puede caer la final. 1 en una estancia (se duerme una noche).
     * - data-af-rango-llenar: llena la final si está vacía. En un filtro no: «desde» sin «hasta» es una búsqueda válida.
     *
     * Solo se mueve la final si quedó antes de lo permitido: nunca se borra lo que alguien ya eligió.
     */
    const sumarDias = (fecha, dias) => {
        const [anio, mes, dia] = fecha.split('-').map(Number);

        return new Date(Date.UTC(anio, mes - 1, dia + dias)).toISOString().slice(0, 10);
    };

    const fechaCompleta = (valor) => /^\d{4}-\d{2}-\d{2}$/.test(valor) && Number(valor.slice(0, 4)) >= 1900;

    const parejas = (nombre, desde) => {
        // Lo más cercano primero: en una temporada repetida cada fila tiene su pareja.
        const ambito = desde.closest('.fi-fo-repeater-item, .fi-modal-window, [data-af-rango-ambito], form') ?? document;

        return ambito.querySelector(`input[data-af-rango-fin="${CSS.escape(nombre)}"]`);
    };

    const inicioDe = (fin) => {
        const ambito = fin.closest('.fi-fo-repeater-item, .fi-modal-window, [data-af-rango-ambito], form') ?? document;

        return ambito.querySelector(`input[data-af-rango-inicio="${CSS.escape(fin.dataset.afRangoFin)}"]`);
    };

    const minimoDe = (inicio) => sumarDias(inicio.value, Number(inicio.dataset.afRangoDias ?? 0));

    const aplicarMinimo = (fin, minimo) => {
        // Un mínimo propio del campo, como «hoy» en el motor, también vale.
        const propio = fin.dataset.afMinimoPropio ?? (fin.dataset.afMinimoPropio = fin.getAttribute('min') ?? '');
        fin.min = propio !== '' && propio > minimo ? propio : minimo;
    };

    const avisar = (campo) => {
        campo.dispatchEvent(new Event('input', { bubbles: true }));
        campo.dispatchEvent(new Event('change', { bubbles: true }));
    };

    document.addEventListener('change', (evento) => {
        const inicio = evento.target;
        if (!(inicio instanceof HTMLInputElement) || !inicio.dataset.afRangoInicio || !fechaCompleta(inicio.value)) {
            return;
        }
        const fin = parejas(inicio.dataset.afRangoInicio, inicio);
        if (fin === null) {
            return;
        }
        const minimo = minimoDe(inicio);
        aplicarMinimo(fin, minimo);
        if (fin.value === '' ? inicio.dataset.afRangoLlenar !== undefined : fin.value < minimo) {
            fin.value = minimo;
            avisar(fin);
        }
    });

    // Livewire vuelve a pintar el campo sin el mínimo: se pone justo antes de abrir el calendario, para que abra en el mes correcto.
    const prepararFin = (evento) => {
        const fin = evento.target;
        if (!(fin instanceof HTMLInputElement) || !fin.dataset.afRangoFin) {
            return;
        }
        const inicio = inicioDe(fin);
        if (inicio !== null && fechaCompleta(inicio.value)) {
            aplicarMinimo(fin, minimoDe(inicio));
        }
    };
    document.addEventListener('focusin', prepararFin);
    document.addEventListener('pointerdown', prepararFin, true);

    /*
     * Variables de un documento o una plantilla: se toca una y queda donde estaba el cursor, en el editor o en el campo de texto.
     */
    let ultimoCampo = null;

    const esTexto = (elemento) => elemento.matches('textarea, input[type="text"]');
    const visible = (elemento) => elemento !== null && elemento.isConnected && elemento.offsetParent !== null;

    document.addEventListener('focusin', (evento) => {
        const objetivo = evento.target;
        if (!(objetivo instanceof Element) || objetivo.closest('[data-af-variables]') !== null || objetivo.closest('form') === null) {
            return;
        }
        const editor = objetivo.closest('.fi-fo-rich-editor');
        // El nombre o el código del documento están en el mismo formulario, y una variable no va ahí.
        if (editor !== null || (esTexto(objetivo) && objetivo.closest('.fi-sc-tabs') !== null)) {
            ultimoCampo = editor ?? objetivo;
        }
    });

    const destinoDe = (panel) => {
        const ambito = panel.closest('form') ?? document;
        if (visible(ultimoCampo) && ambito.contains(ultimoCampo)) {
            return ultimoCampo;
        }
        // Sin haber tocado nada: el cuerpo que se está viendo.
        return [...ambito.querySelectorAll('.fi-fo-rich-editor, textarea')].find(visible) ?? null;
    };

    const insertar = (destino, texto) => {
        if (destino.matches('.fi-fo-rich-editor')) {
            const raiz = destino.querySelector('[x-data^="richEditorFormComponent"]') ?? destino.closest('[x-data^="richEditorFormComponent"]');
            const editor = raiz !== null && window.Alpine ? window.Alpine.$data(raiz).getEditor() : null;
            if (editor) {
                editor.chain().focus().insertContent(texto).run();

                return true;
            }

            return false;
        }
        const inicio = destino.selectionStart ?? destino.value.length;
        const fin = destino.selectionEnd ?? inicio;
        destino.setRangeText(texto, inicio, fin, 'end');
        destino.dispatchEvent(new Event('input', { bubbles: true }));
        destino.focus();

        return true;
    };

    document.addEventListener('click', (evento) => {
        const boton = evento.target instanceof Element ? evento.target.closest('[data-af-variable]') : null;
        if (boton === null) {
            return;
        }
        const panel = boton.closest('[data-af-variables]');
        const destino = destinoDe(panel);
        if (destino !== null && insertar(destino, boton.dataset.afVariable)) {
            const estado = panel.querySelector('[data-af-variables-estado]');
            if (estado !== null) {
                estado.textContent = `${panel.dataset.afInsertada} ${boton.dataset.afVariable}`;
            }
        }
    });

    const sinAcentos = (texto) => texto.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

    document.addEventListener('input', (evento) => {
        const campo = evento.target;
        if (!(campo instanceof HTMLInputElement) || campo.dataset.afVariablesBuscar === undefined) {
            return;
        }
        const busqueda = sinAcentos(campo.value.trim());
        campo.closest('[data-af-variables]').querySelectorAll('details').forEach((grupo) => {
            let hay = false;
            grupo.querySelectorAll('li').forEach((fila) => {
                const coincide = busqueda === '' || sinAcentos(fila.textContent).includes(busqueda);
                fila.hidden = !coincide;
                hay = hay || coincide;
            });
            grupo.hidden = !hay;
            if (busqueda !== '') {
                grupo.open = hay;
            }
        });
    });

    /*
     * Ordenar las tablas propias (.af-tabla) con un clic en el título de la columna. Ordena lo que ya está en la pantalla.
     * Lo que se lee distinto de como se ordena, como una fecha en palabras, lleva su valor en data-orden.
     */
    const estados = new WeakMap();

    const comoNumero = (texto) => {
        const limpio = texto.replace(/ /g, ' ').trim();
        // Con moneda o porcentaje, pero sin palabras: «12 noches» se ordena como texto, y «$1,200.50» o «1.200,50 €», como número.
        if (!/^-?(?:\p{Lu}{1,3})?[^\p{L}\d-]*-?\d[\d.,\s']*(?:\s?(?:%|[^\p{L}\d\s]|\p{Lu}{3}))?$/u.test(limpio)) {
            return null;
        }
        const cifras = limpio.replace(/[^\d.,-]/g, '');
        const ultimoPunto = cifras.lastIndexOf('.');
        const ultimaComa = cifras.lastIndexOf(',');
        const separador = Math.max(ultimoPunto, ultimaComa);
        // Con los dos, el último es el decimal; con uno solo, tres cifras detrás son miles: «1,200» y «1.200».
        const tieneDecimales = separador !== -1 && ((ultimoPunto !== -1 && ultimaComa !== -1) || cifras.length - separador - 1 !== 3);
        const entero = (tieneDecimales ? cifras.slice(0, separador) : cifras).replace(/[.,]/g, '');
        const numero = Number(tieneDecimales ? `${entero}.${cifras.slice(separador + 1)}` : entero);

        return Number.isNaN(numero) ? null : numero;
    };

    const valorDeCelda = (fila, columna) => {
        const celda = fila.cells[columna];
        if (celda === undefined) {
            return '';
        }
        const texto = celda.dataset.orden ?? celda.textContent;

        return texto.trim().replace(/\s+/g, ' ');
    };

    const vacio = (valor) => valor === '' || valor === '—' || valor === '-';

    // data-orden ya viene sin formato: «10.125» son diez y pico, no diez mil.
    const numeroDeCelda = (fila, columna, valor) => {
        if (fila.cells[columna]?.dataset.orden === undefined) {
            return comoNumero(valor);
        }

        return /^-?\d+(?:\.\d+)?$/.test(valor) ? Number(valor) : null;
    };

    const ordenar = (tabla, columna, direccion) => {
        const comparador = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
        for (const cuerpo of tabla.tBodies) {
            const filas = [...cuerpo.rows];
            const valores = new Map(filas.map((fila) => [fila, valorDeCelda(fila, columna)]));
            const numeros = new Map(filas.map((fila) => [fila, numeroDeCelda(fila, columna, valores.get(fila))]));
            const todoNumero = filas.every((fila) => vacio(valores.get(fila)) || numeros.get(fila) !== null);
            const ordenadas = filas.map((fila, indice) => [fila, indice]).sort(([a, ia], [b, ib]) => {
                const va = valores.get(a);
                const vb = valores.get(b);
                // Lo vacío siempre al final, en las dos direcciones.
                if (vacio(va) !== vacio(vb)) {
                    return vacio(va) ? 1 : -1;
                }
                const resultado = todoNumero ? (numeros.get(a) ?? 0) - (numeros.get(b) ?? 0) : comparador.compare(va, vb);

                return (resultado === 0 ? ia - ib : resultado * direccion);
            }).map(([fila]) => fila);
            if (ordenadas.some((fila, indice) => fila !== filas[indice])) {
                ordenadas.forEach((fila) => cuerpo.appendChild(fila));
            }
        }
    };

    const prepararTabla = (tabla) => {
        const filas = [...tabla.tBodies].flatMap((cuerpo) => [...cuerpo.rows]);
        if (filas.length < 2 || filas.some((fila) => [...fila.cells].some((celda) => celda.colSpan > 1)) || tabla.tHead === null) {
            return;
        }
        for (const titulo of tabla.tHead.querySelectorAll('th')) {
            // Un título con solo texto para lectores de pantalla es la columna de los botones: no hay nada que ordenar.
            const textoVisible = [...titulo.childNodes].filter((nodo) => !nodo.classList?.contains('af-sr')).map((nodo) => nodo.textContent).join('').trim();
            if (titulo.querySelector('button, a, input') !== null || textoVisible === '' || titulo.dataset.afSinOrden !== undefined) {
                continue;
            }
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'af-orden';
            while (titulo.firstChild) {
                boton.appendChild(titulo.firstChild);
            }
            titulo.appendChild(boton);
        }
        const estado = estados.get(tabla);
        if (estado !== undefined) {
            const titulo = tabla.tHead.querySelectorAll('th')[estado.posicion];
            if (titulo !== undefined) {
                titulo.setAttribute('aria-sort', estado.direccion === 1 ? 'ascending' : 'descending');
                ordenar(tabla, titulo.cellIndex, estado.direccion);
            }
        }
    };

    document.addEventListener('click', (evento) => {
        const boton = evento.target instanceof Element ? evento.target.closest('button.af-orden') : null;
        if (boton === null) {
            return;
        }
        const titulo = boton.closest('th');
        const tabla = boton.closest('table');
        const titulos = [...tabla.tHead.querySelectorAll('th')];
        const direccion = titulo.getAttribute('aria-sort') === 'ascending' ? -1 : 1;
        titulos.forEach((otro) => otro.removeAttribute('aria-sort'));
        titulo.setAttribute('aria-sort', direccion === 1 ? 'ascending' : 'descending');
        estados.set(tabla, { posicion: titulos.indexOf(titulo), direccion });
        ordenar(tabla, titulo.cellIndex, direccion);
    });

    const prepararTablas = () => document.querySelectorAll('table.af-tabla').forEach(prepararTabla);

    let programado = false;
    const programar = () => {
        if (!programado) {
            programado = true;
            requestAnimationFrame(() => {
                programado = false;
                prepararTablas();
            });
        }
    };

    const arrancar = () => {
        prepararTablas();
        // Livewire vuelve a pintar las tablas al cambiar un filtro: se les devuelve el orden elegido.
        new MutationObserver(programar).observe(document.body, { childList: true, subtree: true });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', arrancar, { once: true });
    } else {
        arrancar();
    }
})();
