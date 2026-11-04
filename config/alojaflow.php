<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [

    // Versión instalada; la usa el enlace «Código fuente» (regla 8).
    'version' => '1.0.4',

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

    // Actualizaciones desde el panel (D-033; docs/especificacion/instalacion-actualizaciones.md).
    'actualizaciones' => [
        // Clave pública Ed25519 de quien publica las versiones: solo se instalan paquetes firmados
        // con su clave secreta. Quien distribuya su propia versión de AlojaFlow pone aquí la suya.
        'clave_publica' => env('ALOJAFLOW_CLAVE_PUBLICA_ACTUALIZACIONES', 'lC/ZImhUArZodVfJSVyjddbGKVsJp1b47kDSMdCql10='),
        // En una copia de desarrollo (con .git) el código se actualiza con git, no desde el panel.
        'permitir_en_copia_git' => (bool) env('ALOJAFLOW_ACTUALIZAR_COPIA_GIT', false),
        'tamano_maximo_paquete' => 300 * 1024 * 1024,
        'tamano_maximo_descomprimido' => 900 * 1024 * 1024,
        'archivos_maximos' => 60000,
        // Copias (base de datos y archivos sustituidos) que se guardan: las de las últimas N.
        'copias_conservadas' => 3,
        // Requisitos mínimos de PHP que comprueba cada paquete además de los suyos.
        'extensiones' => ['ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'sodium', 'tokenizer', 'xml', 'zip', 'zlib'],
    ],

];
