<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Programador;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Contracts\Foundation\Application;

/**
 * Que ninguna tarea arranque a media actualización (D-172). schedule:run elige las tareas al empezar la ronda y solo
 * entonces revisa el mantenimiento. Si una actualización entra en mantenimiento mientras la ronda sigue, las tareas
 * que faltan arrancan con los archivos y las cachés a medio cambiar, y fallan. Con este filtro, cada una lo revisa
 * justo antes de empezar, como ya hace tareas-pendientes. Las que corren en mantenimiento (el latido, terminar la
 * actualización) siguen igual.
 */
final class MantenimientoEnCadaTarea
{
    /**
     * @param  array<Event>  $tareas
     */
    public static function aplicar(array $tareas): void
    {
        foreach ($tareas as $tarea) {
            if (! $tarea->runsInMaintenanceMode()) {
                $tarea->skip(fn (Application $app): bool => $app->isDownForMaintenance());
            }
        }
    }
}
