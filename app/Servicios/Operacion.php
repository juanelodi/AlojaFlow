<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\Propiedad;
use App\Models\Reserva;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Consultas del día a día: «Hoy» y el resumen de propiedades (M3).
 */
final class Operacion
{
    /**
     * Reservas (no bloqueos) vigentes de las propiedades, con lo que muestran las pantallas.
     *
     * @param  list<int>  $propiedades
     * @return Builder<Reserva>
     */
    public static function reservas(array $propiedades): Builder
    {
        return Reserva::query()
            ->vigentes()
            ->where('tipo', TipoReserva::Reserva->value)
            ->whereIn('propiedad_id', $propiedades)
            ->with(['unidad', 'titular.contactos', 'propiedad', 'principal']);
    }

    /**
     * Llegadas, salidas y huéspedes que siguen alojados en una fecha.
     *
     * @param  list<int>  $propiedades
     * @return array{llegan: Collection<int, Reserva>, salen: Collection<int, Reserva>, continuan: Collection<int, Reserva>}
     */
    public static function dia(array $propiedades, string $fecha): array
    {
        return [
            'llegan' => self::reservas($propiedades)->whereDate('llegada', $fecha)->orderBy('propiedad_id')->orderBy('unidad_id')->get(),
            'salen' => self::reservas($propiedades)->whereDate('salida', $fecha)->orderBy('propiedad_id')->orderBy('unidad_id')->get(),
            'continuan' => self::reservas($propiedades)->whereDate('llegada', '<', $fecha)->whereDate('salida', '>', $fecha)
                ->orderBy('propiedad_id')->orderBy('unidad_id')->get(),
        ];
    }

    /**
     * Tarjeta del resumen de una propiedad.
     *
     * @return array<string, mixed>
     */
    public static function resumen(Propiedad $propiedad): array
    {
        $hoy = $propiedad->hoy();
        $fecha = $hoy->format('Y-m-d');
        $base = fn (): Builder => self::reservas([$propiedad->id])->without(['unidad', 'titular', 'propiedad', 'principal']);

        return [
            'propiedad' => $propiedad,
            'hoy' => $hoy,
            'llegan' => $base()->whereDate('llegada', $fecha)->count(),
            'salen' => $base()->whereDate('salida', $fecha)->count(),
            'alojados' => $base()->whereDate('llegada', '<=', $fecha)->whereDate('salida', '>', $fecha)->count(),
            'unidades' => $propiedad->unidades()->where('activa', true)->count(),
            'llegadas_semana' => $base()->whereDate('llegada', '>', $fecha)->whereDate('llegada', '<=', $hoy->addDays(7)->format('Y-m-d'))->count(),
            'sin_confirmar' => $base()->where('estado', EstadoReserva::SinConfirmar->value)
                ->whereDate('llegada', '>=', $fecha)->whereDate('llegada', '<=', $hoy->addDays(3)->format('Y-m-d'))->count(),
            'pendiente' => (int) $base()->where('pendiente', '>', 0)
                ->whereDate('llegada', '<=', $hoy->addDays(7)->format('Y-m-d'))
                ->whereDate('salida', '>=', $hoy->subDays(7)->format('Y-m-d'))->sum('pendiente'),
            'principal' => $propiedad->principal,
            'avisos' => Aviso::query()->where('propiedad_id', $propiedad->id)->whereNull('resuelto_en')->orderByDesc('abierto_en')->get(),
        ];
    }

    public static function hoyDe(?Propiedad $propiedad): CarbonImmutable
    {
        return $propiedad?->hoy() ?? CarbonImmutable::now((string) (auth()->user()->zona_horaria ?? config('app.timezone')))->startOfDay();
    }
}
