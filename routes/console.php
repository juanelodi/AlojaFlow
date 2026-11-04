<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Programador\TareasPorVisita;
use Illuminate\Support\Facades\Schedule;

// Un solo cron lanza el programador (hosting compartido; CLAUDE.md). Si no hay cron, las mismas
// tareas corren al usar el sistema: App\Programador\TareasPorVisita.
Schedule::call(fn () => TareasPorVisita::anotarLatido())->name('latido')->everyMinute()->evenInMaintenanceMode();
Schedule::command('alojaflow:sincronizar')->everyMinute()->withoutOverlapping(60);
Schedule::command('alojaflow:reservas-vencidas')->everyFiveMinutes()->withoutOverlapping(10);
Schedule::command('alojaflow:limpiar')->dailyAt('03:30');
Schedule::command('alojaflow:copia --programada')->dailyAt('03:10')->withoutOverlapping(60);

// Una actualización que se quedó sin terminar (se cerró el navegador entre las dos fases) o que
// se cortó a medias: se termina o se deshace aquí, también en mantenimiento.
Schedule::command('alojaflow:finalizar-actualizacion --pendientes')->everyMinute()->evenInMaintenanceMode()->withoutOverlapping(60);
