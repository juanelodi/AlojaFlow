<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Models\Unidad;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Unidades libres entre dos fechas para un número de personas (M2).
 */
final class Disponibilidad
{
    /**
     * @param  list<int>  $propiedades
     * @return Collection<int, Unidad>
     */
    public static function libres(array $propiedades, string $llegada, string $salida, int $personas): Collection
    {
        return Unidad::query()->with('propiedad')
            ->whereIn('propiedad_id', $propiedades)
            ->where('activa', true)
            // capacidad 0 = la principal no la indicó: se muestra igual.
            ->where(fn ($q) => $q->where('capacidad', '>=', $personas)->orWhere('capacidad', 0))
            ->whereNotExists(function (Builder $q) use ($llegada, $salida): void {
                $q->selectRaw('1')->from('reservas_noches')
                    ->whereColumn('reservas_noches.unidad_id', 'unidades.id')
                    ->where('reservas_noches.fecha', '>=', $llegada)
                    ->where('reservas_noches.fecha', '<', $salida);
            })
            ->orderBy('propiedad_id')->orderBy('orden')->orderBy('nombre')
            ->get();
    }

    /**
     * Unidades de una propiedad con alguna noche ocupada entre dos fechas, sin contar una reserva.
     *
     * @return list<int>
     */
    public static function ocupadas(int $propiedadId, string $llegada, string $salida, ?int $sinContarReserva = null): array
    {
        return DB::table('reservas_noches')
            ->where('propiedad_id', $propiedadId)
            ->where('fecha', '>=', $llegada)
            ->where('fecha', '<', $salida)
            ->when($sinContarReserva !== null, fn ($q) => $q->where('reserva_id', '!=', $sinContarReserva))
            ->distinct()->pluck('unidad_id')->map(fn (mixed $id): int => (int) $id)->values()->all();
    }
}
