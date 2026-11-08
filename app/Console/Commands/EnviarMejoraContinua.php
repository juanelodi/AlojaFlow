<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\MejoraContinua\EnvioMejora;
use Illuminate\Console\Command;

/**
 * Cada hora: reportes pendientes, errores juntados y, una vez al día, el latido (D-150). Sin permiso de
 * mejora continua solo salen los reportes que alguien escribió.
 */
class EnviarMejoraContinua extends Command
{
    protected $signature = 'alojaflow:mejora-continua {--latido : Manda el latido aunque no le toque}';

    protected $description = 'Manda a dev.alojaflow.com los datos de mejora continua permitidos';

    public function handle(EnvioMejora $envio): int
    {
        $resumen = $envio->ejecutar((bool) $this->option('latido'));
        $this->info(__('mejora.consola.resumen', [
            'reportes' => (string) $resumen['reportes'],
            'errores' => (string) $resumen['errores'],
            'latido' => (string) $resumen['latido'],
        ]));

        return self::SUCCESS;
    }
}
