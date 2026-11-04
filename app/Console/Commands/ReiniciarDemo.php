<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Demo\ModoDemo;
use App\Instalacion\Instalador;
use App\Support\Ajustes;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Deja la demostración pública como nueva. Borra toda la base, por eso se niega a correr si la
 * instalación no está en modo demo.
 */
class ReiniciarDemo extends Command
{
    /** Fuera de la base de datos: el reinicio borra todo lo que hay en ella. */
    private const MARCA = 'app/private/demo-reinicio.txt';

    protected $signature = 'alojaflow:demo-reiniciar {--si-toca : Solo si hoy no se ha reiniciado}';

    protected $description = 'Borra los datos de la demostración pública y la deja como nueva';

    public function handle(Instalador $instalador): int
    {
        if (! ModoDemo::activo()) {
            $this->error(__('consola.demo_no_activa'));

            return self::FAILURE;
        }
        $hoy = now('UTC')->format('Y-m-d');
        $marca = storage_path(self::MARCA);
        if ($this->option('si-toca') && File::exists($marca) && trim(File::get($marca)) === $hoy) {
            return self::SUCCESS;
        }
        File::ensureDirectoryExists(dirname($marca));
        File::put($marca, $hoy);

        if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0) {
            $this->error(trim(Artisan::output()));

            return self::FAILURE;
        }
        File::deleteDirectory(storage_path('app/private/archivos'));
        Ajustes::olvidar();

        $instalador->migrar();
        $instalador->crearAdministrador('Demo', ModoDemo::CORREO, ModoDemo::CONTRASENA);
        $instalador->crearDemo();
        $this->info(__('consola.demo_reiniciada'));

        return self::SUCCESS;
    }
}
