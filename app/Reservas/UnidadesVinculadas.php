<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Reservas;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Unidades vinculadas (D-155): la casa completa y sus cuartos. Dos unidades no se pueden ocupar a la vez si una es parte de la otra
 * o si las dos tienen una parte en común (la casa y la planta alta). Un solo nivel: lo que tiene partes no es parte de nada.
 */
final class UnidadesVinculadas
{
    /**
     * Con qué otras choca cada una. Solo aparecen las que tienen alguna.
     *
     * @param  list<int>  $unidades
     * @return array<int, list<int>>
     */
    public static function mapa(array $unidades): array
    {
        if ($unidades === []) {
            return [];
        }
        $filas = DB::table('unidades_partes')->whereIn('unidad_id', $unidades)->orWhereIn('parte_id', $unidades)->get(['unidad_id', 'parte_id']);
        if ($filas->isEmpty()) {
            return [];
        }
        // Las otras unidades que comparten alguna de esas partes.
        $partes = $filas->whereIn('unidad_id', $unidades)->pluck('parte_id')->all();
        $filas = $filas->merge($partes === [] ? [] : DB::table('unidades_partes')->whereIn('parte_id', $partes)->get(['unidad_id', 'parte_id']));

        $mapa = [];
        foreach ($unidades as $unidad) {
            $suyas = $filas->where('unidad_id', $unidad)->pluck('parte_id')->map(fn (mixed $id): int => (int) $id)->all();
            $choca = [
                ...$suyas,
                ...$filas->where('parte_id', $unidad)->pluck('unidad_id')->map(fn (mixed $id): int => (int) $id)->all(),
                ...$filas->whereIn('parte_id', $suyas)->pluck('unidad_id')->map(fn (mixed $id): int => (int) $id)->all(),
            ];
            $choca = array_values(array_unique(array_filter($choca, fn (int $id): bool => $id !== $unidad)));
            if ($choca !== []) {
                sort($choca);
                $mapa[$unidad] = $choca;
            }
        }

        return $mapa;
    }

    /**
     * @return list<int>
     */
    public static function conflictos(int $unidad): array
    {
        return self::mapa([$unidad])[$unidad] ?? [];
    }

    /**
     * Cuáles de estas unidades tienen algo entre la llegada y la salida: una reserva o, si se pide, un bloqueo de otro calendario.
     *
     * @param  list<int>  $unidades
     * @return list<int>
     */
    public static function ocupadas(array $unidades, CarbonImmutable $llegada, CarbonImmutable $salida, bool $conExternos = true): array
    {
        if ($unidades === []) {
            return [];
        }
        $desde = $llegada->format('Y-m-d');
        $hasta = $salida->format('Y-m-d');
        $ocupadas = DB::table('reservas_noches')->whereIn('unidad_id', $unidades)->where('fecha', '>=', $desde)->where('fecha', '<', $hasta)
            ->distinct()->pluck('unidad_id')->map(fn (mixed $id): int => (int) $id)->all();
        if ($conExternos) {
            $ocupadas = [...$ocupadas, ...DB::table('bloqueos_externos')->whereIn('unidad_id', $unidades)->whereNull('eliminado_en')
                ->where('llegada', '<', $hasta)->where('salida', '>', $desde)
                ->distinct()->pluck('unidad_id')->map(fn (mixed $id): int => (int) $id)->all()];
        }

        return array_values(array_unique($ocupadas));
    }
}
