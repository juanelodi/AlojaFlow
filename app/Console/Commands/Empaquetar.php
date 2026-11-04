<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\Empaquetador;
use App\Actualizacion\Revisor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Number;
use RuntimeException;

/**
 * Construye dist/alojaflow-x.y.z.zip, el paquete firmado de la versión de config/alojaflow.php,
 * y lo revisa como lo haría una instalación (instalacion-actualizaciones.md §2). Solo en la
 * copia de desarrollo, con la clave secreta de firma.
 */
class Empaquetar extends Command
{
    protected $signature = 'alojaflow:empaquetar
        {--clave= : Archivo con la clave secreta de firma (por omisión .herramientas/clave-actualizaciones.secreta)}
        {--composer= : composer.phar (por omisión .herramientas/composer.phar)}
        {--salida= : Carpeta del zip (por omisión dist/)}
        {--permitir-cambios : Empaquetar aunque haya cambios sin confirmar en git (solo para pruebas)}';

    protected $description = 'Construye el paquete firmado (zip) de la versión actual para instalar o actualizar';

    public function handle(Empaquetador $empaquetador, Revisor $revisor): int
    {
        $raiz = base_path();
        $version = (string) config('alojaflow.version');

        try {
            if (! is_dir($raiz.'/.git')) {
                throw new RuntimeException('Se empaqueta desde la copia de desarrollo (git), no desde una instalación.');
            }
            $cambios = trim(Process::path($raiz)->run(['git', 'status', '--porcelain'])->output());
            if ($cambios !== '' && ! $this->option('permitir-cambios')) {
                throw new RuntimeException("Hay cambios sin confirmar en git; confírmalos antes de publicar la versión (o usa --permitir-cambios para una prueba):\n".$cambios);
            }
            if (! preg_match('/^## '.preg_quote($version, '/').'\b/m', (string) @file_get_contents($raiz.'/CAMBIOS.md'))) {
                $this->warn("CAMBIOS.md no tiene la sección «## {$version}».");
            }

            $archivoClave = (string) ($this->option('clave') ?? $raiz.'/.herramientas/clave-actualizaciones.secreta');
            $clave = @file_get_contents($archivoClave);
            if (! is_string($clave)) {
                throw new RuntimeException("No se encuentra la clave secreta de firma ($archivoClave).");
            }
            $composer = (string) ($this->option('composer') ?? $raiz.'/.herramientas/composer.phar');
            if (! is_file($composer)) {
                throw new RuntimeException("No se encuentra composer ($composer).");
            }

            $this->info("Paquete de AlojaFlow $version");
            if ($this->callSilently('filament:assets') !== self::SUCCESS) {
                throw new RuntimeException('php artisan filament:assets falló.');
            }
            $resultado = $empaquetador->empaquetar(
                $raiz,
                $version,
                $clave,
                (string) config('alojaflow.actualizaciones.clave_publica'),
                $composer,
                (string) ($this->option('salida') ?? $raiz.'/dist'),
                sys_get_temp_dir().'/alojaflow-empaquetado-'.bin2hex(random_bytes(4)),
                fn (string $paso) => $this->line($paso),
            );
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        // Se revisa como lo haría una instalación vacía de una versión anterior.
        $vacia = sys_get_temp_dir().'/alojaflow-revision-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($vacia);
        try {
            $revision = $revisor->revisar($resultado['zip'], $vacia, '0.0.0', (string) config('alojaflow.actualizaciones.clave_publica'), null);
        } finally {
            File::deleteDirectory($vacia);
        }
        if (! $revision->valida()) {
            $this->error('El paquete no pasa la revisión: '.implode(' ', $revision->errores));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Paquete listo y revisado.');
        $this->table(['', ''], [
            ['Zip', $resultado['zip']],
            ['SHA-256', $resultado['sha256']],
            ['Instalador', $resultado['instalador']],
            ['Archivos', (string) $resultado['archivos']],
            ['Tamaño', Number::fileSize((int) filesize($resultado['zip']), 1).' (descomprimido '.Number::fileSize($resultado['bytes'], 1).')'],
        ]);
        $this->line('Para actualizar una instalación: súbelo en Sistema → '.__('actualizaciones.titulo').', o en su consola:');
        $this->line('  php artisan alojaflow:actualizar /ruta/a/'.basename($resultado['zip']));
        $this->line('Para instalar desde cero: sube '.basename($resultado['instalador']).' al hosting y ábrelo en el navegador.');

        return self::SUCCESS;
    }
}
