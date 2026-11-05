<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeZone;

/**
 * Formatos según el idioma del usuario: nunca fijos en el código (regla 7).
 */
final class Formato
{
    /**
     * @return array<string, string> código => nombre del idioma en su propio idioma
     */
    public static function idiomas(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English',
            'fr' => 'Français',
            'it' => 'Italiano',
            'pt' => 'Português',
            'de' => 'Deutsch',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function zonasHorarias(): array
    {
        $zonas = DateTimeZone::listIdentifiers();

        return array_combine($zonas, $zonas);
    }

    public static function dinero(int $importe, string $moneda): string
    {
        return Dinero::formatear($importe, $moneda);
    }

    public static function fecha(?CarbonInterface $fecha, string $formato = 'll'): string
    {
        return $fecha === null ? '—' : $fecha->locale(app()->getLocale())->isoFormat($formato);
    }

    /**
     * @return array<int, string> del lunes (1) al domingo (7), en el idioma de quien mira
     */
    public static function diasDeLaSemana(): array
    {
        $dias = [];
        for ($i = 1; $i <= 7; $i++) {
            $dias[$i] = (string) CarbonImmutable::create(2024, 1, $i)?->locale(app()->getLocale())->isoFormat('dddd');
        }

        return $dias;
    }

    public static function fechaCorta(?CarbonInterface $fecha): string
    {
        return self::fecha($fecha, 'ddd D MMM');
    }

    public static function fechaLarga(?CarbonInterface $fecha): string
    {
        return self::fecha($fecha, 'dddd, LL');
    }

    /**
     * Fecha y hora en la zona del usuario (o la indicada).
     */
    public static function momento(?CarbonInterface $momento, ?string $zona = null): string
    {
        if ($momento === null) {
            return '—';
        }
        $zona ??= auth()->user()->zona_horaria ?? config('app.timezone');

        return $momento->copy()->setTimezone((string) $zona)->locale(app()->getLocale())->isoFormat('lll');
    }

    public static function porcentaje(?float $valor): string
    {
        return $valor === null ? '—' : number_format($valor * 100, 1, ',' === self::separadorDecimal() ? ',' : '.', '').' %';
    }

    /**
     * Un porcentaje que alguien escribió, como la comisión de un acuerdo: tal cual, sin decimales de más.
     */
    public static function tasa(float|string|null $valor): string
    {
        return str_replace('.', self::separadorDecimal(), (string) (float) $valor).' %';
    }

    public static function separadorDecimal(): string
    {
        return in_array(app()->getLocale(), ['en', 'es'], true) ? '.' : ',';
    }
}
