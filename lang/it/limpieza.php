<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Pulizie',
    'tarea_salida' => 'Pulire :unidad (partenza)',
    'tarea_estancia' => 'Pulire :unidad (ospite in struttura)',
    'solo_mias' => 'Solo le mie',
    'vacio' => 'Niente da mostrare.',
    'sale_hoy' => 'Parte oggi.',
    'llega_hoy' => 'Arriva oggi.',
    'alojada' => 'Ospite in struttura.',
    'libre' => 'Libera.',
    'fuera_de_servicio' => 'Fuori servizio',
    'urgente_corto' => 'Urgente',
    'ver_foto' => 'Foto :n',
    'empezar' => 'Inizia',
    'terminar' => 'Termina',
    'marcar_limpia' => 'Segna pulita',
    'marcar_sucia' => 'Segna sporca',
    'volver_servicio' => 'Rimetti in servizio',
    'poner_fuera' => 'Metti fuori servizio',
    'marcada' => ':unidad: :estado.',
    'terminada' => 'Fatto: :tarea.',
    'incidencia' => 'Segnala un problema',
    'incidencia_de' => 'Problema in :unidad',
    'incidencia_reportar' => 'Segnala',
    'incidencia_titulo' => 'Che cosa succede?',
    'incidencia_titulo_ayuda' => 'Per esempio: la doccia perde.',
    'incidencia_descripcion' => 'Dettagli',
    'incidencia_ok' => 'Problema segnalato.',
    'fotos' => 'Foto',
    'urgente' => 'È urgente',
    'dejar_fuera' => 'Lascia l’unità fuori servizio (non si potrà vendere)',
    'aviso_whatsapp' => 'Invia via WhatsApp',
    'aviso_titulo' => ':propiedad · pulizie del :fecha',
    'aviso_vacio' => 'Nessuna attività.',
    'tareas' => [
        'modelo' => 'attività',
        'modelos' => 'Attività',
        'tipo' => 'Tipo',
        'titulo' => 'Attività',
        'descripcion' => 'Descrizione',
        'fecha' => 'Data',
        'estado' => 'Stato',
        'unidad' => 'Unità',
        'asignada' => 'Assegnata a',
        'sin_asignar' => 'Non assegnata',
        'urgente' => 'Urgente',
        'vacio' => 'Nessuna attività.',
        'abiertas' => 'Da fare',
    ],
    'regla' => [
        'accion' => 'Pulizia durante il soggiorno',
        'frecuencia' => 'Ogni quanto?',
        'sin_limpieza' => 'Solo alla partenza',
        'dias' => 'Giorni',
        'fechas' => 'Date',
        'guardada' => 'Pulizia del soggiorno salvata.',
    ],
    'inspeccion' => 'Controlla le unità dopo averle pulite',
    'inspeccion_ayuda' => 'Un’unità pulita resta «Da controllare» finché qualcuno non la ispeziona.',
];
