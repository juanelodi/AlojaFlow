<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\TipoReserva;
use App\Limpieza\Omisiones;
use App\Models\Aviso;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        return Limites::reservasVisibles(Reserva::query()
            ->vigentes()
            ->where('tipo', TipoReserva::Reserva->value)
            ->whereIn('propiedad_id', $propiedades)
            ->with(['unidad', 'titular.contactos', 'propiedad', 'principal', 'marcaEstancia']), Acceso::usuarioActual());
    }

    /**
     * Las reservas de la jornada: sin los clientes omitidos en «Hoy» (D-144). Para buscar una reserva por su número, `reservas()`.
     *
     * @param  list<int>  $propiedades
     * @return Builder<Reserva>
     */
    public static function delDia(array $propiedades): Builder
    {
        return Omisiones::excluir(self::reservas($propiedades), 'titular_id', $propiedades, LugarOmision::Hoy);
    }

    /**
     * Llegadas, salidas y huéspedes que siguen alojados en una fecha.
     *
     * @param  list<int>  $propiedades
     * @return array{llegan: Collection<int, Reserva>, salen: Collection<int, Reserva>, continuan: Collection<int, Reserva>}
     */
    public static function dia(array $propiedades, string $fecha): array
    {
        $omitidos = Omisiones::huespedes($propiedades, LugarOmision::Hoy);
        $base = fn (): Builder => Omisiones::sinEstos(self::reservas($propiedades), 'titular_id', $omitidos);

        return [
            'llegan' => $base()->where('llegada', $fecha)->orderBy('propiedad_id')->orderBy('unidad_id')->get(),
            'salen' => $base()->where('salida', $fecha)->orderBy('propiedad_id')->orderBy('unidad_id')->get(),
            'continuan' => $base()->where('llegada', '<', $fecha)->where('salida', '>', $fecha)
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
        $omitidos = Omisiones::huespedes([$propiedad->id], LugarOmision::Hoy);
        $base = fn (): Builder => Omisiones::sinEstos(self::reservas([$propiedad->id]), 'titular_id', $omitidos)->without(['unidad', 'titular', 'propiedad', 'principal']);

        return [
            'propiedad' => $propiedad,
            'hoy' => $hoy,
            'llegan' => $base()->where('llegada', $fecha)->count(),
            'salen' => $base()->where('salida', $fecha)->count(),
            'alojados' => $base()->where('llegada', '<=', $fecha)->where('salida', '>', $fecha)->count(),
            'unidades' => $propiedad->unidades()->where('activa', true)->count(),
            'llegadas_semana' => $base()->where('llegada', '>', $fecha)->where('llegada', '<=', $hoy->addDays(7)->format('Y-m-d'))->count(),
            'sin_confirmar' => $base()->where('estado', EstadoReserva::SinConfirmar->value)
                ->where('llegada', '>=', $fecha)->where('llegada', '<=', $hoy->addDays(3)->format('Y-m-d'))->count(),
            'pendiente' => (int) $base()->where('pendiente', '>', 0)
                ->where('llegada', '<=', $hoy->addDays(7)->format('Y-m-d'))
                ->where('salida', '>=', $hoy->subDays(7)->format('Y-m-d'))->sum('pendiente'),
            // Lo que suele pasar desapercibido: quien sale con saldo, y lo que se pisa en una subordinada.
            'salidas_con_saldo' => $base()->where('pendiente', '>', 0)->where('salida', '<', $fecha)->where('salida', '>=', $hoy->subDays(7)->format('Y-m-d'))->count(),
            'solapes' => $propiedad->esIndependiente() ? 0 : self::solapes($propiedad->id, $fecha),
            'principal' => $propiedad->principal,
            'avisos' => Aviso::query()->where('propiedad_id', $propiedad->id)->whereNull('resuelto_en')->orderByDesc('abierto_en')->get(),
        ];
    }

    /**
     * Noches de aquí en adelante con dos reservas en la misma unidad. En una independiente no pueden existir; en una subordinada, la principal lo permite.
     */
    private static function solapes(int $propiedadId, string $desde): int
    {
        $pisadas = DB::table('reservas_noches as n')->join('reservas as r', 'r.id', '=', 'n.reserva_id')->where('n.propiedad_id', $propiedadId)->where('n.fecha', '>=', $desde)
            ->where('r.tipo', TipoReserva::Reserva->value)->whereNull('r.eliminada_en_principal_en')
            ->groupBy('n.unidad_id', 'n.fecha')->havingRaw('COUNT(*) > 1')->select('n.unidad_id', 'n.fecha');

        return DB::query()->fromSub($pisadas, 'pisadas')->count();
    }

    public static function hoyDe(?Propiedad $propiedad): CarbonImmutable
    {
        return $propiedad?->hoy() ?? CarbonImmutable::now((string) (auth()->user()->zona_horaria ?? config('app.timezone')))->startOfDay();
    }
}
