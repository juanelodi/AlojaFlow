<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Ical\GestorIcal;
use Illuminate\Console\Command;

/**
 * Lee los calendarios de los portales y revisa los choques.
 */
class AtenderIcal extends Command
{
    protected $signature = 'alojaflow:ical';

    protected $description = 'Lee los calendarios iCal de los portales y avisa de los choques con las reservas';

    public function handle(GestorIcal $ical): int
    {
        $this->info(__('consola.calendarios_leidos', ['cuantos' => $ical->leerPendientes()]));

        return self::SUCCESS;
    }
}
