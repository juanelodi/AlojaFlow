<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [

    // Versión instalada; la usa el enlace «Código fuente» (regla 8).
    'version' => '1.0.42',

    // Idiomas de la interfaz (regla 7). El español es la fuente.
    'idiomas' => ['es', 'en', 'fr', 'it', 'pt', 'de'],

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
        // Dónde se pregunta cuál es la última versión (D-140). Es un GET sin ningún dato de la instalación.
        'url_ultima_version' => env('ALOJAFLOW_URL_ULTIMA_VERSION', 'https://api.github.com/repos/juanelodi/AlojaFlow/releases/latest'),
        // Actualizaciones urgentes (D-151): a qué versión pasar cuando el equipo de AlojaFlow corrige una vulnerabilidad.
        // Es un GET sin ningún dato de la instalación, y solo se instala una versión firmada y más nueva. Vacío: no se consulta.
        'url_urgente' => env('ALOJAFLOW_URL_URGENTE', 'https://dev.alojaflow.com/api/v1/actualizacion-urgente'),
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

    // Mejora continua (D-150; docs/especificacion/mejora-continua.md): datos de diagnóstico y uso, sin
    // huéspedes, solo con permiso de quien administra la instalación. Los reportes los manda quien los escribe.
    // El servicio alojado (D-162): el código del cliente, que AlojaFlow App escribe en el .env de su instancia.
    'instancia' => [
        'codigo' => env('ALOJAFLOW_CODIGO_INSTANCIA'),
    ],

    'mejora_continua' => [
        // Quien distribuya su propia versión de AlojaFlow pone aquí su receptor, o lo deja vacío para no mandar nada.
        'url' => env('ALOJAFLOW_MEJORA_URL', 'https://dev.alojaflow.com/api/v1'),
        // En el servicio alojado lo decide el contrato, no una casilla (D-168): por omisión, sí.
        'alojada' => (bool) env('ALOJAFLOW_MEJORA_CONTINUA', true),
        'tiempo_conexion' => 5,
        'tiempo_peticion' => 20,
        'agente' => 'AlojaFlow/%s (mejora continua; +https://alojaflow.com)',
        // Errores por envío y días que se guardan los que no se pudieron mandar.
        'errores_por_envio' => 100,
        'dias_conservar' => 7,
        // Visitas a pantallas: días que se guardan (el latido manda los últimos 30).
        'dias_pantallas' => 35,
        'imagenes_por_reporte' => 10,
        'kb_por_imagen' => 10240,
    ],

];
