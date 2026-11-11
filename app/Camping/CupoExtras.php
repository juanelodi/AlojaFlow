<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Camping;

use App\Enums\EstadoReserva;
use App\Models\CargoReserva;
use App\Models\ConceptoCargo;
use App\Reservas\ReservaRechazada;
use Carbon\CarbonImmutable;

/**
 * Bicicletas, kayaks, leña: lo que hay un número fijo cada día (D-113). Lo apartado es por noche de cada reserva.
 */
final class CupoExtras
{
    /**
     * Cuántos quedan para todas esas noches: el peor día es el que manda.
     */
    public static function libres(ConceptoCargo $concepto, CarbonImmutable $llegada, CarbonImmutable $salida, ?int $sinReserva = null): ?int
    {
        if ($concepto->cupo_diario === null) {
            return null;
        }
        $desde = $llegada->format('Y-m-d');
        $hasta = $salida->format('Y-m-d');
        $apartados = CargoReserva::query()->with('reserva:id,llegada,salida')->where('concepto_cargo_id', $concepto->id)
            ->whereHas('reserva', fn ($q) => $q->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en')
                ->where('llegada', '<', $hasta)->where('salida', '>', $desde)
                ->when($sinReserva !== null, fn ($r) => $r->whereKeyNot($sinReserva)))
            ->get();

        $porDia = [];
        foreach ($apartados as $cargo) {
            $inicio = max($desde, $cargo->reserva->llegada->format('Y-m-d'));
            $fin = min($hasta, $cargo->reserva->salida->format('Y-m-d'));
            for ($dia = CarbonImmutable::parse($inicio); $dia->format('Y-m-d') < $fin; $dia = $dia->addDay()) {
                $porDia[$dia->format('Y-m-d')] = ($porDia[$dia->format('Y-m-d')] ?? 0) + (float) $cargo->cantidad;
            }
        }

        return max(0, $concepto->cupo_diario - (int) ceil($porDia === [] ? 0 : max($porDia)));
    }

    /**
     * Con las filas bloqueadas: dos huéspedes a la vez no se llevan la última bicicleta.
     *
     * @param  array<int, float>  $elegidos  id del concepto => cantidad
     */
    public static function apartar(array $elegidos, CarbonImmutable $llegada, CarbonImmutable $salida): void
    {
        $conCupo = ConceptoCargo::query()->whereKey(array_keys($elegidos))->whereNotNull('cupo_diario')->orderBy('id')->lockForUpdate()->get();
        foreach ($conCupo as $concepto) {
            if ($elegidos[$concepto->id] > (self::libres($concepto, $llegada, $salida) ?? 0)) {
                throw new ReservaRechazada(__('camping.extras.agotado', ['extra' => $concepto->nombre]));
            }
        }
    }
}
