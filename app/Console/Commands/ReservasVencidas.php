<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoReserva;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Illuminate\Console\Command;

/**
 * Libera las reservas del motor que nadie pagó ni confirmó a tiempo.
 */
class ReservasVencidas extends Command
{
    protected $signature = 'alojaflow:reservas-vencidas';

    protected $description = 'Cancela las reservas del motor de reservas que no se pagaron ni confirmaron a tiempo';

    public function handle(GestorReservas $gestor): int
    {
        $canceladas = 0;
        Reserva::query()->whereNotNull('expira_en')->where('expira_en', '<', now('UTC'))
            ->where('estado', EstadoReserva::SinConfirmar->value)->where('pagado', 0)->whereNull('entrada_registrada_en')
            ->each(function (Reserva $reserva) use ($gestor, &$canceladas): void {
                try {
                    $gestor->cancelar($reserva, porVencimiento: true);
                    $canceladas++;
                } catch (ReservaRechazada $e) {
                    report($e);
                }
            });
        $this->info(__('consola.vencidas_canceladas', ['cuantas' => $canceladas]));

        return self::SUCCESS;
    }
}
