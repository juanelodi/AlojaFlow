<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Campos;

use Carbon\CarbonImmutable;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Throwable;

/**
 * Dos campos de fecha que van juntos: el final nunca queda antes que el inicial y su calendario abre en el mes correcto. Lo hace
 * resources/js/alojaflow.js en el navegador, sin esperar al servidor; `moverFin` es lo mismo del lado del servidor, para los formularios que recalculan.
 */
final class RangoFechas
{
    /**
     * @return array<string, string|int>
     */
    public static function inicio(string $rango, int $dias = 0, bool $llenar = false): array
    {
        return ['data-af-rango-inicio' => $rango, 'data-af-rango-dias' => $dias] + ($llenar ? ['data-af-rango-llenar' => '1'] : []);
    }

    /**
     * @return array<string, string>
     */
    public static function fin(string $rango): array
    {
        return ['data-af-rango-fin' => $rango];
    }

    /**
     * Llegada y salida: se duerme al menos una noche, y la salida se llena sola.
     *
     * @return array<string, string|int>
     */
    public static function llegada(string $rango = 'estancia'): array
    {
        return self::inicio($rango, 1, true);
    }

    public static function moverFin(Get $get, Set $set, string $inicio, string $fin, int $dias = 1): void
    {
        $corregido = self::finCorregido($get($inicio), $get($fin), $dias);
        if ($corregido !== null) {
            $set($fin, $corregido);
        }
    }

    /**
     * La fecha final que le toca, o null si la que tiene ya está bien: nunca se cambia lo que no hace falta.
     */
    public static function finCorregido(mixed $inicio, mixed $fin, int $dias = 1): ?string
    {
        $fecha = self::leer($inicio);
        if ($fecha === null) {
            return null;
        }
        $minimo = $fecha->addDays($dias);
        $actual = self::leer($fin);

        return $actual === null || $actual->lt($minimo) ? $minimo->format('Y-m-d') : null;
    }

    private static function leer(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || $valor === '') {
            return null;
        }
        try {
            return CarbonImmutable::parse($valor)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
