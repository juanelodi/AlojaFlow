<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [

    // Versión instalada; la usa el enlace «Código fuente» (regla 8).
    'version' => '1.0.23',

    // Idiomas de la interfaz (regla 7). El español es la fuente.
    'idiomas' => ['es', 'en', 'fr', 'it', 'pt', 'de'],

    // Quién timbra los CFDI. Para conectar un PAC: implementar App\Facturacion\ConectorPac (D-049).
    'pac' => App\Facturacion\SinPac::class,

    // Registro de auditoría: lleva dirección y navegador del personal (modelo-datos.md §8).
    'dias_conservar_auditoria' => 365,

    // Obliga a todo el personal a usar la verificación en dos pasos.
    'exigir_dos_pasos' => filter_var(env('ALOJAFLOW_EXIGIR_DOS_PASOS', false), FILTER_VALIDATE_BOOLEAN),

    // Demostración pública: solo en una instalación aparte, sin datos reales (D-044).
    'modo_demo' => filter_var(env('ALOJAFLOW_MODO_DEMO', false), FILTER_VALIDATE_BOOLEAN),

    'subordinacion' => [
        // Permite principales por http:// y en redes privadas. Solo para desarrollo.
        'red_local' => filter_var(env('ALOJAFLOW_SUBORDINACION_RED_LOCAL', false), FILTER_VALIDATE_BOOLEAN),
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
        // De dónde baja el zip el instalador chico. El paquete va firmado: el lugar no tiene por qué ser de confianza (D-071).
        'url_paquete' => env('ALOJAFLOW_URL_PAQUETE', 'https://github.com/juanelodi/AlojaFlow/releases/download/v:version/alojaflow-:version.zip'),
        // En una copia de desarrollo (con .git) el código se actualiza con git, no desde el panel.
        'permitir_en_copia_git' => filter_var(env('ALOJAFLOW_ACTUALIZAR_COPIA_GIT', false), FILTER_VALIDATE_BOOLEAN),
        'tamano_maximo_paquete' => 300 * 1024 * 1024,
        'tamano_maximo_descomprimido' => 900 * 1024 * 1024,
        'archivos_maximos' => 60000,
        // Copias (base de datos y archivos sustituidos) que se guardan: las de las últimas N.
        'copias_conservadas' => 3,
        // Requisitos mínimos de PHP que comprueba cada paquete además de los suyos.
        'extensiones' => ['ctype', 'curl', 'dom', 'fileinfo', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'sodium', 'tokenizer', 'xml', 'zip', 'zlib'],
    ],

];
