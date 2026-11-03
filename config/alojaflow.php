<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [

    // Versión instalada; la usa el enlace «Código fuente» (regla 8).
    'version' => '0.1.0',

    // Idiomas de la interfaz (regla 7). El español es la fuente.
    'idiomas' => ['es', 'en', 'fr', 'it', 'pt', 'de'],

    'subordinacion' => [
        // Permite principales por http:// y en redes privadas. Solo para desarrollo.
        'red_local' => (bool) env('ALOJAFLOW_SUBORDINACION_RED_LOCAL', false),
        'tiempo_conexion' => 15,
        'tiempo_peticion' => 180,
        'tamano_maximo_descarga' => 60 * 1024 * 1024,
        'tamano_maximo_descomprimido' => 300 * 1024 * 1024,
        'tamano_maximo_linea' => 32 * 1024 * 1024,
        'lote' => 500,
        'dias_conservar_ejecuciones' => 90,
        'fallos_para_avisar' => 3,
        'agente' => 'AlojaFlow/%s (subordinada; +https://alojaflow.com)',
    ],

    // Copias reales de HotelDruid para pruebas locales; nunca dentro del repositorio (regla 12).
    'copias_reales' => env('ALOJAFLOW_COPIAS_REALES'),

];
