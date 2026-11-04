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
                $this->warn(__('consola.paquete.sin_cambios', ['version' => $version]));
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

            $this->info(__('consola.paquete.de', ['version' => $version]));
            if ($this->callSilently('filament:assets') !== self::SUCCESS) {
                throw new RuntimeException('php artisan filament:assets falló.');
            }
            if ($this->callSilently('vendor:publish', ['--tag' => 'livewire:assets', '--force' => true]) !== self::SUCCESS) {
                throw new RuntimeException('php artisan vendor:publish --tag=livewire:assets falló.');
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
            $this->error(__('consola.paquete.no_pasa', ['errores' => implode(' ', $revision->errores)]));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(__('consola.paquete.listo'));
        $this->table(['', ''], [
            ['Zip', $resultado['zip']],
            ['SHA-256', $resultado['sha256']],
            [__('consola.paquete.instalador'), $resultado['instalador']],
            [__('consola.paquete.archivos'), (string) $resultado['archivos']],
            [__('consola.paquete.tamano'), __('consola.paquete.tamano_valor', ['zip' => Number::fileSize((int) filesize($resultado['zip']), 1), 'descomprimido' => Number::fileSize($resultado['bytes'], 1)])],
        ]);
        $this->line(__('consola.paquete.para_actualizar', ['pantalla' => __('actualizaciones.titulo')]));
        $this->line('  php artisan alojaflow:actualizar /ruta/a/'.basename($resultado['zip']));
        $this->line(__('consola.paquete.para_instalar', ['archivo' => basename($resultado['instalador'])]));

        return self::SUCCESS;
    }
}
