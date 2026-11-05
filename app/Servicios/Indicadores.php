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
use App\Models\Propiedad;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ocupación, ADR, RevPAR e ingresos por origen y por mes, sobre las noches ocupadas (M9).
 * Los ingresos son el total de cada reserva repartido por noches; los bloqueos ocupan pero
 * no bajan el ADR (como el plugin kpi de 1.2.x).
 */
final class Indicadores
{
    /**
     * @param  list<int>  $propiedades
     * @return list<array<string, mixed>>
     */
    public static function porPropiedad(array $propiedades, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $dias = (int) $desde->diffInDays($hasta) + 1;
        $resultado = [];

        foreach (Propiedad::query()->whereIn('id', $propiedades)->orderBy('nombre')->get() as $propiedad) {
            $unidades = $propiedad->unidades()->where('activa', true)->count();
            $noches = self::noches($propiedad->id, $desde, $hasta)
                ->selectRaw('COUNT(*) as ocupadas')
                ->selectRaw('SUM(CASE WHEN r.tipo = ? AND n.importe > 0 THEN 1 ELSE 0 END) as con_importe', [TipoReserva::Reserva->value])
                ->selectRaw('SUM(CASE WHEN r.tipo = ? THEN n.importe ELSE 0 END) as ingresos', [TipoReserva::Reserva->value])
                ->first();
            $reservas = Omisiones::excluir(DB::table('reservas'), 'titular_id', [$propiedad->id], LugarOmision::Informes)->where('propiedad_id', $propiedad->id)->whereNull('eliminada_en_principal_en')
                ->where('tipo', TipoReserva::Reserva->value)
                ->whereBetween('llegada', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')])
                ->selectRaw('SUM(CASE WHEN estado <> ? THEN 1 ELSE 0 END) as reservas', [EstadoReserva::Cancelada->value])
                ->selectRaw('AVG(CASE WHEN estado <> ? THEN noches END) as estancia', [EstadoReserva::Cancelada->value])
                ->selectRaw('SUM(CASE WHEN estado = ? THEN 1 ELSE 0 END) as canceladas', [EstadoReserva::Cancelada->value])
                ->first();

            $disponibles = $unidades * $dias;
            $ocupadas = (int) ($noches->ocupadas ?? 0);
            $conImporte = (int) ($noches->con_importe ?? 0);
            $ingresos = (int) ($noches->ingresos ?? 0);

            $resultado[] = [
                'propiedad' => $propiedad,
                'moneda' => $propiedad->moneda,
                'unidades' => $unidades,
                'disponibles' => $disponibles,
                'ocupadas' => $ocupadas,
                'ocupacion' => $disponibles > 0 ? $ocupadas / $disponibles : null,
                'ingresos' => $ingresos,
                'adr' => $conImporte > 0 ? intdiv($ingresos, $conImporte) : null,
                'revpar' => $disponibles > 0 ? intdiv($ingresos, $disponibles) : null,
                'reservas' => (int) ($reservas->reservas ?? 0),
                'estancia_media' => $reservas?->estancia === null ? null : round((float) $reservas->estancia, 1),
                'canceladas' => (int) ($reservas->canceladas ?? 0),
                'por_origen' => self::porOrigen($propiedad->id, $desde, $hasta),
                'por_mes' => self::noches($propiedad->id, $desde, $hasta)
                    ->selectRaw("DATE_FORMAT(n.fecha, '%Y-%m') as mes")
                    ->selectRaw('COUNT(*) as noches')
                    ->selectRaw('SUM(CASE WHEN r.tipo = ? THEN n.importe ELSE 0 END) as ingresos', [TipoReserva::Reserva->value])
                    ->groupBy('mes')->orderBy('mes')->get()->all(),
            ];
        }

        return $resultado;
    }

    /**
     * Noches e ingresos por origen; vacío y nulo cuentan como «sin origen».
     *
     * @return list<object{origen: string, noches: int, ingresos: int}>
     */
    private static function porOrigen(int $propiedadId, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $grupos = [];
        $filas = self::noches($propiedadId, $desde, $hasta)
            ->where('r.tipo', TipoReserva::Reserva->value)
            ->select('r.origen_texto')->selectRaw('COUNT(*) as noches')->selectRaw('SUM(n.importe) as ingresos')
            ->groupBy('r.origen_texto')->get();
        foreach ($filas as $fila) {
            $origen = trim((string) $fila->origen_texto) !== '' ? (string) $fila->origen_texto : __('ui.indicadores.sin_origen');
            $grupos[$origen] ??= ['noches' => 0, 'ingresos' => 0];
            $grupos[$origen]['noches'] += (int) $fila->noches;
            $grupos[$origen]['ingresos'] += (int) $fila->ingresos;
        }
        uasort($grupos, fn (array $a, array $b): int => $b['ingresos'] <=> $a['ingresos']);

        $resultado = [];
        foreach ($grupos as $origen => $datos) {
            $resultado[] = (object) ['origen' => (string) $origen, 'noches' => $datos['noches'], 'ingresos' => $datos['ingresos']];
        }

        return $resultado;
    }

    private static function noches(int $propiedadId, CarbonImmutable $desde, CarbonImmutable $hasta): \Illuminate\Database\Query\Builder
    {
        $consulta = DB::table('reservas_noches as n')
            ->join('reservas as r', 'r.id', '=', 'n.reserva_id')
            ->where('n.propiedad_id', $propiedadId)
            ->whereBetween('n.fecha', [$desde->format('Y-m-d'), $hasta->format('Y-m-d')]);

        return Omisiones::excluir($consulta, 'r.titular_id', [$propiedadId], LugarOmision::Informes);
    }
}
