<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Limpieza\GestorLimpieza;
use App\Models\Propiedad;
use Illuminate\Console\Command;

/**
 * Tareas de limpieza a partir de las reservas, y unidades sucias tras las salidas (§4.7).
 */
class GenerarTareasLimpieza extends Command
{
    protected $signature = 'alojaflow:limpieza';

    protected $description = 'Crea las tareas de limpieza de los próximos días y marca sucias las unidades tras la salida';

    public function handle(GestorLimpieza $limpieza): int
    {
        $nuevas = 0;
        Propiedad::query()->where('activa', true)->each(function (Propiedad $propiedad) use ($limpieza, &$nuevas): void {
            $limpieza->atenderSalidas($propiedad);
            $nuevas += $limpieza->generarTareas($propiedad);
        });
        $this->info("Tareas nuevas: $nuevas");

        return self::SUCCESS;
    }
}
