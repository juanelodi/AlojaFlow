<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Instalacion\Instancia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
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

/**
 * Corre un comando como lo llama AlojaFlow App y lee su respuesta JSON.
 *
 * @param  array<string, mixed>  $opciones
 * @return array{0: int, 1: array<string, mixed>}
 */
function comandoJson(string $comando, array $opciones): array
{
    $salida = new BufferedOutput();
    $codigo = Artisan::call($comando, $opciones + ['--json' => true], $salida);
    $lineas = array_filter(array_map('trim', explode("\n", $salida->fetch())), fn (string $linea): bool => str_starts_with($linea, '{'));
    $json = json_decode((string) end($lineas), true);

    return [$codigo, is_array($json) ? $json : []];
}

/**
 * Hace como si la aplicación corriera como instancia del servicio alojado (D-162), sin arrancar otro proceso. Con
 * null vuelve a ser una instalación normal; TestCase lo deshace al terminar cada prueba.
 */
function comoInstancia(?string $carpeta = '/instancias/casa'): void
{
    (new ReflectionProperty(Instancia::class, 'carpeta'))->setValue(null, $carpeta);
}
