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

    /** @var array<string, string> */
    private static array $separadores = [];

    public static function decimales(string $moneda): int
    {
        return self::$decimales[$moneda] ??= (function () use ($moneda): int {
            $formato = new NumberFormatter('en@currency='.$moneda, NumberFormatter::CURRENCY);
            $digitos = $formato->getAttribute(NumberFormatter::FRACTION_DIGITS);

            return $digitos >= 0 ? $digitos : 2;
        })();
    }

    /**
     * El que lleva esa moneda en el idioma de quien mira: el mismo con que la pantalla enseña ese dinero.
     */
    public static function separadorDecimal(string $moneda): string
    {
        $locale = self::locale($moneda);

        return self::$separadores[$locale] ??= (string) (new NumberFormatter($locale, NumberFormatter::DECIMAL))->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL);
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

    /**
     * De la unidad menor a un decimal en texto («1234.50»), para los campos de los formularios.
     */
    public static function aDecimal(int $importe, string $moneda): string
    {
        $decimales = self::decimales($moneda);

        return number_format($importe / (10 ** $decimales), $decimales, '.', '');
    }

    public static function formatear(int $importe, string $moneda, ?string $locale = null): string
    {
        $formato = new NumberFormatter($locale ?? self::locale($moneda), NumberFormatter::CURRENCY);
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

    /**
     * Idioma del usuario con la región de la moneda (es + MXN → es_MX: «$99,300.00»), para que
     * cada moneda se escriba como en su país.
     */
    private static function locale(?string $moneda = null): string
    {
        $idioma = str_replace('-', '_', app()->getLocale());
        if (str_contains($idioma, '_') || $moneda === null) {
            return $idioma;
        }
        $region = self::REGION_POR_MONEDA[$moneda] ?? null;

        return $region === null ? $idioma : $idioma.'_'.$region;
    }

    /** Región habitual de cada moneda, para su formato. */
    private const REGION_POR_MONEDA = [
        'MXN' => 'MX', 'USD' => 'US', 'EUR' => 'ES', 'COP' => 'CO', 'ARS' => 'AR', 'CLP' => 'CL', 'PEN' => 'PE',
        'UYU' => 'UY', 'GTQ' => 'GT', 'CRC' => 'CR', 'DOP' => 'DO', 'BOB' => 'BO', 'PYG' => 'PY', 'BRL' => 'BR',
        'CAD' => 'CA', 'GBP' => 'GB', 'CHF' => 'CH',
    ];
}
