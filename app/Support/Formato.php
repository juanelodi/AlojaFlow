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

    /**
     * Por omisión, la corta de las tablas: «3 feb 2027», sin los «de» ni los puntos que le dan dos renglones en una columna angosta.
     */
    public static function fecha(?CarbonInterface $fecha, string $formato = 'll'): string
    {
        if ($fecha === null) {
            return '—';
        }
        $texto = $fecha->locale(app()->getLocale())->isoFormat($formato);

        return $formato === 'll' ? self::compactar($texto) : $texto;
    }

    /**
     * «3 de feb. de 2027» se vuelve «3 feb 2027».
     */
    public static function compactar(string $fecha): string
    {
        return str_replace(' de ', ' ', str_replace('.', '', $fecha));
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

    /**
     * «lun, mar, sáb», en el idioma de quien mira.
     *
     * @param  list<int>  $dias  del lunes (1) al domingo (7)
     */
    public static function diasCortos(array $dias): string
    {
        sort($dias);

        return implode(', ', array_map(fn (int $dia): string => (string) CarbonImmutable::create(2024, 1, $dia)?->locale(app()->getLocale())->isoFormat('ddd'), $dias));
    }

    public static function fechaCorta(?CarbonInterface $fecha): string
    {
        return str_replace('.', '', self::fecha($fecha, 'ddd D MMM'));
    }

    /**
     * Como la corta, con el año: una reserva puede ser de otro.
     */
    public static function fechaCortaConAnio(?CarbonInterface $fecha): string
    {
        return self::compactar(self::fecha($fecha, 'ddd ll'));
    }

    /**
     * 06/11/26 en México y 11/06/26 en Estados Unidos: el orden y el separador los da el idioma de quien mira.
     */
    public static function fechaNumerica(?CarbonInterface $fecha, bool $anioCompleto = false, bool $conAnio = true): string
    {
        if ($fecha === null) {
            return '—';
        }
        $localizada = $fecha->locale(app()->getLocale());
        $patron = (string) ($localizada->getIsoFormats()['L'] ?? 'DD/MM/YYYY');
        if (! $conAnio) {
            $patron = trim((string) preg_replace('/\W?Y+\W?/', '', $patron));
        } elseif (! $anioCompleto) {
            $patron = str_replace('YYYY', 'YY', $patron);
        }

        return $localizada->isoFormat($patron);
    }

    public static function fechaLarga(?CarbonInterface $fecha): string
    {
        return self::fecha($fecha, 'dddd, LL');
    }

    /**
     * La hora como se lee en el idioma de quien mira (8:05 en español, 8:05 AM en inglés), en la zona indicada.
     */
    public static function hora(CarbonInterface $momento, ?string $zona = null): string
    {
        $momento = $zona === null ? $momento : $momento->copy()->setTimezone($zona);

        return $momento->locale(app()->getLocale())->isoFormat('LT');
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
