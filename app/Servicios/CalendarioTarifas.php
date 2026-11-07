<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Models\Propiedad;
use App\Models\Restriccion;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Reservas\Cotizador;
use Carbon\CarbonImmutable;

/**
 * Lo que vale cada tarifa cada noche de un mes, por propiedad (D-148). Calcula con la misma regla que cobra una reserva.
 */
final class CalendarioTarifas
{
    /**
     * @param  list<int>  $propiedades
     * @return array{dias: list<CarbonImmutable>, grupos: list<array{propiedad: Propiedad, filas: list<array{tarifa: Tarifa, celdas: list<array{fecha: string, precio: int, temporada: ?string, estancia_minima: int, cerrado_llegada: bool, cerrado_salida: bool}>}>}>}
     */
    public static function mes(array $propiedades, CarbonImmutable $inicio): array
    {
        $inicio = $inicio->startOfMonth();
        $fin = $inicio->endOfMonth()->startOfDay();
        $dias = [];
        for ($dia = $inicio; $dia->lte($fin); $dia = $dia->addDay()) {
            $dias[] = $dia;
        }
        $tarifas = Tarifa::query()->with('base')->whereIn('propiedad_id', $propiedades)->where('activa', true)->orderBy('nombre')->get()->groupBy('propiedad_id');
        $temporadas = TemporadaTarifa::query()->whereIn('tarifa_id', Tarifa::query()->whereIn('propiedad_id', $propiedades)->select('id'))
            ->where('desde', '<=', $fin->format('Y-m-d'))->where('hasta', '>=', $inicio->format('Y-m-d'))
            ->orderByDesc('desde')->orderByDesc('id')->get()->groupBy('tarifa_id');
        // Las de toda la propiedad: las de una unidad no dicen nada de la tarifa.
        $restricciones = Restriccion::query()->whereIn('propiedad_id', $propiedades)->where('activa', true)->whereNull('unidad_id')
            ->where('desde', '<=', $fin->format('Y-m-d'))->where('hasta', '>=', $inicio->format('Y-m-d'))->get()->groupBy('propiedad_id');

        $grupos = [];
        foreach (Propiedad::query()->whereIn('id', $propiedades)->orderBy('nombre')->get() as $propiedad) {
            $filas = [];
            $cierres = $restricciones->get($propiedad->id, collect());
            foreach ($tarifas->get($propiedad->id, collect()) as $tarifa) {
                $celdas = [];
                $deLaTarifa = $temporadas->get($tarifa->tarifa_base_id ?? $tarifa->id, collect());
                foreach ($dias as $dia) {
                    $fecha = $dia->format('Y-m-d');
                    $noche = Cotizador::precioDeNoche($tarifa, $deLaTarifa, $dia);
                    $cubren = $cierres->filter(fn (Restriccion $r): bool => $r->desde->format('Y-m-d') <= $fecha && $r->hasta->format('Y-m-d') >= $fecha);
                    $celdas[] = [
                        'fecha' => $fecha,
                        'precio' => $noche['precio'],
                        'temporada' => $noche['temporada']?->nombre,
                        'estancia_minima' => $noche['estancia_minima'],
                        'cerrado_llegada' => $cubren->contains(fn (Restriccion $r): bool => (bool) $r->cerrado_llegada),
                        'cerrado_salida' => $cubren->contains(fn (Restriccion $r): bool => (bool) $r->cerrado_salida),
                    ];
                }
                $filas[] = ['tarifa' => $tarifa, 'celdas' => $celdas];
            }
            $grupos[] = ['propiedad' => $propiedad, 'filas' => $filas];
        }

        return ['dias' => $dias, 'grupos' => $grupos];
    }
}
