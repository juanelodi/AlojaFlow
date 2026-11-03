<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Un solo cron por minuto lanza el programador (hosting compartido; CLAUDE.md).
Schedule::command('alojaflow:sincronizar')->everyMinute()->withoutOverlapping(60);
Schedule::command('alojaflow:limpiar')->dailyAt('03:30');
