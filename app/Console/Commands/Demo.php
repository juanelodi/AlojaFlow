<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Demo\GeneradorDemo;
use Illuminate\Console\Command;

/**
 * Crea o quita la propiedad de demostración con datos inventados (D-037).
 */
class Demo extends Command
{
    protected $signature = 'alojaflow:demo {--quitar : Quita la propiedad de demostración y todos sus datos}';

    protected $description = 'Crea o quita una propiedad de demostración con datos ficticios';

    public function handle(GeneradorDemo $generador): int
    {
        if ($this->option('quitar')) {
            $quitada = $generador->eliminar();
            $this->info($quitada ? __('ui.demo.quitada') : __('ui.demo.no_hay'));

            return self::SUCCESS;
        }

        if (GeneradorDemo::existente() !== null) {
            $this->error(__('ui.demo.ya_existe'));

            return self::FAILURE;
        }

        $propiedad = $generador->crear();
        $this->info(__('ui.demo.creada', ['nombre' => $propiedad->nombre, 'reservas' => $propiedad->reservas()->count()]));

        return self::SUCCESS;
    }
}
