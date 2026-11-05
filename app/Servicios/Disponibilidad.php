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
            ->where(fn ($q) => $q->whereRaw('capacidad + camas_extra >= ?', [$personas])->orWhere('capacidad', 0))
            ->whereNotExists(function (Builder $q): void {
                $q->selectRaw('1')->from('estados_unidad')
                    ->whereColumn('estados_unidad.unidad_id', 'unidades.id')
                    ->where('estados_unidad.fuera_de_servicio', true);
            })
            ->whereNotExists(function (Builder $q) use ($llegada, $salida): void {
                $q->selectRaw('1')->from('reservas_noches')
                    ->whereColumn('reservas_noches.unidad_id', 'unidades.id')
                    ->where('reservas_noches.fecha', '>=', $llegada)
                    ->where('reservas_noches.fecha', '<', $salida);
            })
            ->whereNotExists(function (Builder $q) use ($llegada, $salida): void {
                $q->selectRaw('1')->from('bloqueos_externos')
                    ->whereColumn('bloqueos_externos.unidad_id', 'unidades.id')
                    ->whereNull('bloqueos_externos.eliminado_en')
                    ->where('bloqueos_externos.llegada', '<', $salida)
                    ->where('bloqueos_externos.salida', '>', $llegada);
            })
            ->orderBy('propiedad_id')->orderBy('orden')->orderBy('nombre')
            ->get();
    }

    /**
     * La primera unidad libre según su orden, la que tenga cabida; así se llenan primero las que
     * el alojamiento prefiere (la prioridad de la 1.2.x).
     */
    public static function primeraLibre(int $propiedadId, string $llegada, string $salida, int $personas, ?int $tarifaId = null): ?Unidad
    {
        $libres = self::libres([$propiedadId], $llegada, $salida, $personas);

        return $libres->first(fn (Unidad $u): bool => $tarifaId === null || in_array($tarifaId, $u->tarifasIds(), true));
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
