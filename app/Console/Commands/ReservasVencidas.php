<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoReserva;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Illuminate\Console\Command;

/**
 * Libera las reservas del motor que se retuvieron mientras el huésped pagaba y no llegó a pagar.
 */
class ReservasVencidas extends Command
{
    protected $signature = 'alojaflow:reservas-vencidas';

    protected $description = 'Cancela las reservas del motor de reservas que no se pagaron a tiempo';

    public function handle(GestorReservas $gestor): int
    {
        $canceladas = 0;
        Reserva::query()->whereNotNull('expira_en')->where('expira_en', '<', now('UTC'))
            ->where('estado', EstadoReserva::SinConfirmar->value)->where('pagado', 0)
            ->each(function (Reserva $reserva) use ($gestor, &$canceladas): void {
                try {
                    $gestor->cancelar($reserva);
                    $canceladas++;
                } catch (ReservaRechazada $e) {
                    report($e);
                }
            });
        $this->info("Reservas vencidas canceladas: $canceladas");

        return self::SUCCESS;
    }
}
