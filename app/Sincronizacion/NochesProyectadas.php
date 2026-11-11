<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Enums\EstadoReserva;
use App\Reservas\GestorReservas;
use App\Support\Dinero;
use Carbon\CarbonImmutable;

/**
 * Las noches que ocupa una reserva copiada de una principal.
 */
final class NochesProyectadas
{
    /**
     * Noches de una reserva vigente, con el total repartido (D-019; exclusiva = NULL en una subordinada).
     *
     * @param  array<string, mixed>  $datos
     * @return list<array<string, mixed>>
     */
    public static function de(int $reservaId, array $datos): array
    {
        if ($datos['estado'] === EstadoReserva::Cancelada->value) {
            return [];
        }

        $inicio = CarbonImmutable::parse((string) $datos['llegada']);
        $cuantas = (int) $inicio->diffInDays(CarbonImmutable::parse((string) $datos['salida']));
        // Una fecha absurda en la principal no debe llenar la memoria en cada sincronización.
        $cuantas = min($cuantas, GestorReservas::NOCHES_MAXIMAS);
        $importes = Dinero::repartir((int) $datos['total'], $cuantas);
        $noches = [];
        for ($i = 0; $i < $cuantas; $i++) {
            $noches[] = [
                'reserva_id' => $reservaId,
                'propiedad_id' => $datos['propiedad_id'],
                'unidad_id' => $datos['unidad_id'],
                'fecha' => $inicio->addDays($i)->format('Y-m-d'),
                'importe' => $importes[$i],
                'exclusiva' => null,
            ];
        }

        return $noches;
    }
}
