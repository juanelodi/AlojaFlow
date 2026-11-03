<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Las pruebas de Feature usan MySQL/MariaDB (base alojaflow_pruebas); nunca SQLite.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Las de Unit necesitan la aplicación (configuración, traducciones) pero no la base de datos.
pest()->extend(TestCase::class)
    ->in('Unit');

/**
 * Ruta de un archivo temporal para la prueba en curso.
 */
function archivoTemporal(string $nombre): string
{
    $carpeta = storage_path('framework/testing/'.uniqid('', true));
    if (! is_dir($carpeta)) {
        mkdir($carpeta, 0700, true);
    }

    return $carpeta.'/'.$nombre;
}
