<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Descarga del código fuente de la versión instalada (AGPL §13; regla 8). Las dependencias
 * (vendor/, node_modules/) son programas libres disponibles por separado: composer.lock y
 * package.json dicen sus versiones exactas. Nunca incluye .env, storage/ ni datos.
 */
class CodigoFuenteController extends Controller
{
    /** Lo que forma el código fuente de AlojaFlow. */
    private const INCLUIR = [
        'app', 'bootstrap/app.php', 'bootstrap/providers.php', 'config', 'database', 'docs', 'instalador', 'lang', 'resources', 'routes', 'tests', '.htaccess',
        'public/index.php', 'public/.htaccess', 'public/img', 'public/robots.txt', 'public/favicon.ico', 'branding',
        'artisan', 'composer.json', 'composer.lock', 'package.json', 'phpunit.xml', 'phpstan.neon', 'pint.json', 'vite.config.js',
        'LICENSE', 'THIRD-PARTY-NOTICES.md', 'README.md', 'CAMBIOS.md', 'CLAUDE.md', '.env.example', '.editorconfig', '.gitattributes', '.gitignore',
    ];

    public function __invoke(): BinaryFileResponse
    {
        $version = (string) config('alojaflow.version');
        $ruta = storage_path('app/private/codigo-fuente/alojaflow-'.$version.'.zip');

        // En desarrollo el código cambia sin cambiar de versión: se vuelve a generar siempre.
        if (! is_file($ruta) || app()->isLocal()) {
            $this->generar($ruta, $version);
        }

        return response()->download($ruta, 'alojaflow-'.$version.'-codigo-fuente.zip', ['Content-Type' => 'application/zip']);
    }

    private function generar(string $ruta, string $version): void
    {
        File::ensureDirectoryExists(dirname($ruta), 0700);
        $temporal = $ruta.'.'.bin2hex(random_bytes(4)).'.tmp';
        $zip = new ZipArchive();
        if ($zip->open($temporal, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo del código fuente.');
        }

        $prefijo = 'alojaflow-'.$version.'/';
        foreach (self::INCLUIR as $relativa) {
            $absoluta = base_path($relativa);
            if (is_file($absoluta)) {
                $zip->addFile($absoluta, $prefijo.$relativa);
            } elseif (is_dir($absoluta)) {
                $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absoluta, FilesystemIterator::SKIP_DOTS));
                /** @var SplFileInfo $archivo */
                foreach ($iterador as $archivo) {
                    $nombre = $archivo->getFilename();
                    if ($archivo->isLink() || ! $archivo->isFile() || str_starts_with($nombre, '.env')) {
                        continue;
                    }
                    $dentro = str_replace('\\', '/', substr($archivo->getPathname(), strlen(base_path()) + 1));
                    $zip->addFile($archivo->getPathname(), $prefijo.$dentro);
                }
            }
        }
        $zip->close();
        File::move($temporal, $ruta);
    }
}
