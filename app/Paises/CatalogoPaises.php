<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Enums\PaquetePais;
use Collator;
use DateTimeZone;
use Locale;
use NumberFormatter;

/**
 * Lista de países para elegir al crear una propiedad, con lo que se deduce de cada uno: moneda, zona
 * horaria, idioma y paquete. Los nombres y las monedas salen de la extensión intl, ya obligatoria.
 */
final class CatalogoPaises
{
    private const CODIGOS = [
        'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL',
        'BM', 'BN', 'BO', 'BQ', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU', 'CV',
        'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE', 'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB',
        'GD', 'GE', 'GF', 'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GT', 'GU', 'GW', 'GY', 'HK', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM',
        'IN', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC', 'LI', 'LK',
        'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV',
        'MW', 'MX', 'MY', 'MZ', 'NA', 'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG', 'PH', 'PK', 'PL', 'PM',
        'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW', 'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SK', 'SL', 'SM', 'SN', 'SO',
        'SR', 'SS', 'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'US',
        'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI', 'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW',
    ];

    /** Países con varias zonas: la de la mayoría de la gente, no la primera por orden alfabético. */
    private const ZONAS = [
        'AR' => 'America/Argentina/Buenos_Aires', 'AU' => 'Australia/Sydney', 'BR' => 'America/Sao_Paulo', 'CA' => 'America/Toronto', 'CL' => 'America/Santiago',
        'CN' => 'Asia/Shanghai', 'EC' => 'America/Guayaquil', 'ES' => 'Europe/Madrid', 'ID' => 'Asia/Jakarta', 'KZ' => 'Asia/Almaty', 'MN' => 'Asia/Ulaanbaatar',
        'MX' => 'America/Mexico_City', 'NZ' => 'Pacific/Auckland', 'PT' => 'Europe/Lisbon', 'RU' => 'Europe/Moscow', 'US' => 'America/New_York',
    ];

    private const IDIOMAS = [
        'es' => ['AR', 'BO', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'ES', 'GQ', 'GT', 'HN', 'MX', 'NI', 'PA', 'PE', 'PR', 'PY', 'SV', 'UY', 'VE'],
        'pt' => ['AO', 'BR', 'CV', 'GW', 'MZ', 'PT', 'ST', 'TL'],
        'fr' => ['BF', 'BI', 'BJ', 'CD', 'CF', 'CG', 'CI', 'CM', 'DJ', 'FR', 'GA', 'GF', 'GN', 'GP', 'HT', 'MC', 'MF', 'ML', 'MQ', 'NC', 'NE', 'PF', 'PM', 'RE', 'SN', 'TD', 'TG', 'WF', 'YT'],
        'it' => ['IT', 'SM', 'VA'],
        'de' => ['AT', 'DE', 'LI'],
    ];

    private const PAQUETES = ['MX' => PaquetePais::Mexico, 'ES' => PaquetePais::Espana, 'US' => PaquetePais::EstadosUnidos];

    /**
     * @return array<string, string> código => nombre en el idioma actual, en orden alfabético
     */
    public static function opciones(): array
    {
        $idioma = app()->getLocale();
        $opciones = [];
        foreach (self::CODIGOS as $codigo) {
            $opciones[$codigo] = (string) Locale::getDisplayRegion('und-'.$codigo, $idioma);
        }
        (new Collator($idioma))->asort($opciones);

        return $opciones;
    }

    /**
     * Lo que se propone para una propiedad en ese país. Se puede cambiar después.
     *
     * @return array{moneda: string, zona_horaria: string, idioma: string, paquete_pais: string}
     */
    public static function valoresDe(string $pais): array
    {
        $moneda = (new NumberFormatter('und_'.$pais, NumberFormatter::CURRENCY))->getTextAttribute(NumberFormatter::CURRENCY_CODE);
        $zonas = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $pais);
        $idioma = 'en';
        foreach (self::IDIOMAS as $codigo => $paises) {
            if (in_array($pais, $paises, true)) {
                $idioma = $codigo;
            }
        }

        return [
            'moneda' => strlen($moneda) === 3 ? $moneda : 'USD',
            'zona_horaria' => self::ZONAS[$pais] ?? $zonas[0] ?? 'UTC',
            'idioma' => $idioma,
            'paquete_pais' => (self::PAQUETES[$pais] ?? PaquetePais::Generico)->value,
        ];
    }
}
