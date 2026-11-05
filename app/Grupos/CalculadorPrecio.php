<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Enums\ModoPrecioGrupo;

/**
 * El precio de un grupo (D-095). La lógica viene de `hd_grp_calcula` de la 1.2.3, reescrita: la suma de las reservas es siempre el total del grupo, porque
 * los centavos que no se pueden repartir van a la última. Importes en unidad menor.
 */
final class CalculadorPrecio
{
    /**
     * @param  array<int|string, int>  $porCapacidad  precio por noche según cuántas personas caben
     * @param  list<array{id: int, noches: int, capacidad: int, precio_noche: int|null, base_tarifa: int}>  $habitaciones  las activas
     * @return array{lineas: array<int, array{base: int, descuento: int, gratis: bool, extras: int, total: int}>, subtotal: int, descuento: int, extras: int, total: int}
     */
    public static function calcular(ModoPrecioGrupo $modo, ?int $precioTotal, array $porCapacidad, array $habitaciones, ?int $gratisCada, float $descuentoPorcentaje, int $extras): array
    {
        $bases = self::bases($modo, (int) $precioTotal, $porCapacidad, $habitaciones);
        $gratis = self::gratis($bases, $gratisCada);
        $porcentaje = min(100.0, max(0.0, $descuentoPorcentaje));

        $lineas = [];
        foreach ($bases as $id => $base) {
            $esGratis = in_array($id, $gratis, true);
            $descuento = $esGratis ? $base : (int) round($base * $porcentaje / 100);
            $lineas[$id] = ['base' => $base, 'descuento' => $descuento, 'gratis' => $esGratis, 'extras' => 0, 'total' => $base - $descuento];
        }

        $repartidos = self::repartirExtras($lineas, $extras);
        foreach ($repartidos as $id => $parte) {
            $lineas[$id]['extras'] = $parte;
            $lineas[$id]['total'] += $parte;
        }

        $subtotal = array_sum($bases);
        $descuentoTotal = array_sum(array_column($lineas, 'descuento'));
        // Sin habitaciones activas no hay a quién cobrarle los extras.
        $extrasCobrados = array_sum($repartidos);

        return [
            'lineas' => $lineas,
            'subtotal' => $subtotal,
            'descuento' => $descuentoTotal,
            'extras' => $extrasCobrados,
            'total' => $subtotal - $descuentoTotal + $extrasCobrados,
        ];
    }

    /**
     * @param  array<int|string, int>  $porCapacidad
     * @param  list<array{id: int, noches: int, capacidad: int, precio_noche: int|null, base_tarifa: int}>  $habitaciones
     * @return array<int, int>
     */
    private static function bases(ModoPrecioGrupo $modo, int $precioTotal, array $porCapacidad, array $habitaciones): array
    {
        $bases = [];
        if ($modo === ModoPrecioGrupo::Total) {
            $noches = array_sum(array_column($habitaciones, 'noches'));
            $repartido = 0;
            foreach ($habitaciones as $i => $habitacion) {
                $parte = $i === count($habitaciones) - 1
                    ? $precioTotal - $repartido
                    : ($noches > 0 ? (int) floor($precioTotal * $habitacion['noches'] / $noches) : 0);
                $bases[$habitacion['id']] = $parte;
                $repartido += $parte;
            }

            return $bases;
        }
        foreach ($habitaciones as $habitacion) {
            $bases[$habitacion['id']] = match (true) {
                $habitacion['precio_noche'] !== null => $habitacion['precio_noche'] * $habitacion['noches'],
                $modo === ModoPrecioGrupo::PorCapacidad => (int) ($porCapacidad[$habitacion['capacidad']] ?? 0) * $habitacion['noches'],
                default => $habitacion['base_tarifa'],
            };
        }

        return $bases;
    }

    /**
     * Una gratis cada N: las más baratas, para que el regalo no sea mayor de lo pactado.
     *
     * @param  array<int, int>  $bases
     * @return list<int>
     */
    private static function gratis(array $bases, ?int $cada): array
    {
        if ($cada === null || $cada < 2 || count($bases) < $cada) {
            return [];
        }
        asort($bases);

        return array_slice(array_keys($bases), 0, (int) floor(count($bases) / $cada));
    }

    /**
     * Entre las que pagan, según lo que paga cada una: una habitación regalada se queda en cero.
     *
     * @param  array<int, array{base: int, descuento: int, gratis: bool, extras: int, total: int}>  $lineas
     * @return array<int, int>
     */
    private static function repartirExtras(array $lineas, int $extras): array
    {
        if ($extras <= 0 || $lineas === []) {
            return [];
        }
        $quienes = array_filter($lineas, fn (array $linea): bool => ! $linea['gratis']);
        // Todas regaladas o en cero: alguien tiene que llevar el cargo.
        $quienes = $quienes === [] ? $lineas : $quienes;
        $base = array_sum(array_column($quienes, 'total'));
        $partes = [];
        $repartido = 0;
        $restantes = count($quienes);
        foreach ($quienes as $id => $linea) {
            $restantes--;
            $parte = $restantes === 0
                ? $extras - $repartido
                : (int) floor($extras * ($base > 0 ? $linea['total'] / $base : 1 / count($quienes)));
            $partes[$id] = $parte;
            $repartido += $parte;
        }

        return $partes;
    }
}
