<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EjecucionSincronizacion;
use App\Models\Principal;
use Illuminate\Console\Command;

/**
 * Borra lo vencido según los plazos de conservación (modelo-datos.md §8).
 */
class Limpiar extends Command
{
    protected $signature = 'alojaflow:limpiar';

    protected $description = 'Borra los registros vencidos (ejecuciones de sincronización…)';

    public function handle(): int
    {
        $vigentes = Principal::query()->whereNotNull('ejecucion_vigente_id')->pluck('ejecucion_vigente_id');
        $borradas = EjecucionSincronizacion::query()
            ->where('iniciada_en', '<', now()->subDays((int) config('alojaflow.subordinacion.dias_conservar_ejecuciones')))
            ->whereNotIn('id', $vigentes)
            ->delete();

        $this->info("Ejecuciones de sincronización borradas: $borradas");

        return self::SUCCESS;
    }
}
