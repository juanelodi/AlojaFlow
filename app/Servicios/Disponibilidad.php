<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Models\Unidad;
use App\Reservas\UnidadesVinculadas;
use Carbon\CarbonImmutable;
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
        $libres = Unidad::query()->with('propiedad')
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

        return self::sinVinculadasOcupadas($libres, $llegada, $salida);
    }

    /**
     * Fuera las que tienen ocupada una unidad vinculada (D-155): la casa completa con un cuarto vendido, o un cuarto con la casa vendida.
     *
     * @param  Collection<int, Unidad>  $libres
     * @return Collection<int, Unidad>
     */
    private static function sinVinculadasOcupadas(Collection $libres, string $llegada, string $salida): Collection
    {
        $mapa = UnidadesVinculadas::mapa($libres->map(fn (Unidad $unidad): int => $unidad->id)->all());
        if ($mapa === []) {
            return $libres;
        }
        $ocupadas = UnidadesVinculadas::ocupadas(array_values(array_unique(array_merge(...array_values($mapa)))), CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida));

        return $libres->reject(fn (Unidad $unidad): bool => array_intersect($mapa[$unidad->id] ?? [], $ocupadas) !== [])->values();
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
