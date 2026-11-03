<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use NumberFormatter;

/**
 * Importes en la unidad menor de la moneda (centavos, o lo que toque) — D-018.
 */
final class Dinero
{
    /** @var array<string, int> */
    private static array $decimales = [];

    public static function decimales(string $moneda): int
    {
        return self::$decimales[$moneda] ??= (function () use ($moneda): int {
            $formato = new NumberFormatter('en@currency='.$moneda, NumberFormatter::CURRENCY);
            $digitos = $formato->getAttribute(NumberFormatter::FRACTION_DIGITS);

            return $digitos >= 0 ? $digitos : 2;
        })();
    }

    /**
     * Convierte un decimal en texto («1234.5») a la unidad menor, mitad hacia arriba.
     */
    public static function aUnidadMenor(string|int|float|null $valor, string $moneda): int
    {
        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return 0;
        }

        return (int) round((float) $valor * (10 ** self::decimales($moneda)), 0, PHP_ROUND_HALF_UP);
    }

    public static function formatear(int $importe, string $moneda, ?string $locale = null): string
    {
        $formato = new NumberFormatter($locale ?? self::locale(), NumberFormatter::CURRENCY);
        $texto = $formato->formatCurrency($importe / (10 ** self::decimales($moneda)), $moneda);

        return $texto === false ? $moneda.' '.$importe : $texto;
    }

    /**
     * Reparte un total en partes que suman exactamente el total; el resto va a las primeras.
     *
     * @return list<int>
     */
    public static function repartir(int $total, int $partes): array
    {
        if ($partes <= 0) {
            return [];
        }

        $signo = $total < 0 ? -1 : 1;
        $absoluto = abs($total);
        $base = intdiv($absoluto, $partes);
        $resto = $absoluto % $partes;
        $resultado = [];
        for ($i = 0; $i < $partes; $i++) {
            $resultado[] = $signo * ($base + ($i < $resto ? 1 : 0));
        }

        return $resultado;
    }

    private static function locale(): string
    {
        return str_replace('-', '_', app()->getLocale());
    }
}
