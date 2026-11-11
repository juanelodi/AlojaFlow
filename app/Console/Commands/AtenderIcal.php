<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
