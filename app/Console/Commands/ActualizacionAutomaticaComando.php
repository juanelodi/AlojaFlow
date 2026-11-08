<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\ActualizacionAutomatica;
use Illuminate\Console\Command;

/**
 * Cada hora (D-151): si hay una actualización urgente o, a las 3:00 y con las automáticas prendidas, una
 * versión nueva, la instala. Sin cron solo se entera de la urgente, para avisar en el panel.
 */
class ActualizacionAutomaticaComando extends Command
{
    protected $signature = 'alojaflow:actualizacion-automatica {--solo-consultar : Solo pregunta si hay una actualización urgente}';

    protected $description = 'Instala sola una versión nueva a las 3:00 (si se activó) o una actualización urgente';

    public function handle(ActualizacionAutomatica $automatica): int
    {
        $resultado = $automatica->ejecutar((bool) $this->option('solo-consultar'));
        $this->info(__('actualizaciones.automaticas.consola.'.$resultado));

        return $resultado === 'fallo' ? self::FAILURE : self::SUCCESS;
    }
}
