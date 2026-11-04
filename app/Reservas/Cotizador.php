<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use Carbon\CarbonImmutable;

/**
 * Suma el precio de cada noche: el de la temporada que la cubre o, si no hay, el base de la tarifa.
 * Si dos temporadas se pisan, vale la que empieza más tarde (la más específica).
 */
final class Cotizador
{
    public static function cotizar(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida): ?Cotizacion
    {
        $tarifa = $unidad->tarifa_id === null ? null : Tarifa::query()->where('activa', true)->find($unidad->tarifa_id);
        if ($tarifa === null || $salida->lte($llegada)) {
            return null;
        }

        $temporadas = TemporadaTarifa::query()->where('tarifa_id', $tarifa->id)
            ->whereDate('desde', '<', $salida->format('Y-m-d'))
            ->whereDate('hasta', '>=', $llegada->format('Y-m-d'))
            ->orderByDesc('desde')->orderByDesc('id')
            ->get();

        $noches = [];
        for ($dia = $llegada; $dia->lt($salida); $dia = $dia->addDay()) {
            $temporada = $temporadas->first(fn (TemporadaTarifa $t): bool => $t->desde->lte($dia) && $t->hasta->gte($dia));
            $noches[] = $temporada->precio_noche ?? $tarifa->precio_noche;
        }

        return new Cotizacion($tarifa->nombre, array_sum($noches), $noches);
    }
}
