<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

use App\Enums\PaquetePais;
use Collator;
use DateTimeZone;
use Locale;
use Normalizer;
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

    /** ISO 3166-1 alfa-2 => alfa-3: los registros oficiales piden el de tres letras. */
    private const ALFA3 = [
        'AD' => 'AND', 'AE' => 'ARE', 'AF' => 'AFG', 'AG' => 'ATG', 'AI' => 'AIA', 'AL' => 'ALB', 'AM' => 'ARM', 'AO' => 'AGO', 'AQ' => 'ATA', 'AR' => 'ARG', 'AS' => 'ASM', 'AT' => 'AUT', 'AU' => 'AUS', 'AW' => 'ABW',
        'AX' => 'ALA', 'AZ' => 'AZE', 'BA' => 'BIH', 'BB' => 'BRB', 'BD' => 'BGD', 'BE' => 'BEL', 'BF' => 'BFA', 'BG' => 'BGR', 'BH' => 'BHR', 'BI' => 'BDI', 'BJ' => 'BEN', 'BL' => 'BLM', 'BM' => 'BMU', 'BN' => 'BRN',
        'BO' => 'BOL', 'BQ' => 'BES', 'BR' => 'BRA', 'BS' => 'BHS', 'BT' => 'BTN', 'BV' => 'BVT', 'BW' => 'BWA', 'BY' => 'BLR', 'BZ' => 'BLZ', 'CA' => 'CAN', 'CC' => 'CCK', 'CD' => 'COD', 'CF' => 'CAF', 'CG' => 'COG',
        'CH' => 'CHE', 'CI' => 'CIV', 'CK' => 'COK', 'CL' => 'CHL', 'CM' => 'CMR', 'CN' => 'CHN', 'CO' => 'COL', 'CR' => 'CRI', 'CU' => 'CUB', 'CV' => 'CPV', 'CW' => 'CUW', 'CX' => 'CXR', 'CY' => 'CYP', 'CZ' => 'CZE',
        'DE' => 'DEU', 'DJ' => 'DJI', 'DK' => 'DNK', 'DM' => 'DMA', 'DO' => 'DOM', 'DZ' => 'DZA', 'EC' => 'ECU', 'EE' => 'EST', 'EG' => 'EGY', 'EH' => 'ESH', 'ER' => 'ERI', 'ES' => 'ESP', 'ET' => 'ETH', 'FI' => 'FIN',
        'FJ' => 'FJI', 'FK' => 'FLK', 'FM' => 'FSM', 'FO' => 'FRO', 'FR' => 'FRA', 'GA' => 'GAB', 'GB' => 'GBR', 'GD' => 'GRD', 'GE' => 'GEO', 'GF' => 'GUF', 'GG' => 'GGY', 'GH' => 'GHA', 'GI' => 'GIB', 'GL' => 'GRL',
        'GM' => 'GMB', 'GN' => 'GIN', 'GP' => 'GLP', 'GQ' => 'GNQ', 'GR' => 'GRC', 'GS' => 'SGS', 'GT' => 'GTM', 'GU' => 'GUM', 'GW' => 'GNB', 'GY' => 'GUY', 'HK' => 'HKG', 'HM' => 'HMD', 'HN' => 'HND', 'HR' => 'HRV',
        'HT' => 'HTI', 'HU' => 'HUN', 'ID' => 'IDN', 'IE' => 'IRL', 'IL' => 'ISR', 'IM' => 'IMN', 'IN' => 'IND', 'IO' => 'IOT', 'IQ' => 'IRQ', 'IR' => 'IRN', 'IS' => 'ISL', 'IT' => 'ITA', 'JE' => 'JEY', 'JM' => 'JAM',
        'JO' => 'JOR', 'JP' => 'JPN', 'KE' => 'KEN', 'KG' => 'KGZ', 'KH' => 'KHM', 'KI' => 'KIR', 'KM' => 'COM', 'KN' => 'KNA', 'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT', 'KY' => 'CYM', 'KZ' => 'KAZ', 'LA' => 'LAO',
        'LB' => 'LBN', 'LC' => 'LCA', 'LI' => 'LIE', 'LK' => 'LKA', 'LR' => 'LBR', 'LS' => 'LSO', 'LT' => 'LTU', 'LU' => 'LUX', 'LV' => 'LVA', 'LY' => 'LBY', 'MA' => 'MAR', 'MC' => 'MCO', 'MD' => 'MDA', 'ME' => 'MNE',
        'MF' => 'MAF', 'MG' => 'MDG', 'MH' => 'MHL', 'MK' => 'MKD', 'ML' => 'MLI', 'MM' => 'MMR', 'MN' => 'MNG', 'MO' => 'MAC', 'MP' => 'MNP', 'MQ' => 'MTQ', 'MR' => 'MRT', 'MS' => 'MSR', 'MT' => 'MLT', 'MU' => 'MUS',
        'MV' => 'MDV', 'MW' => 'MWI', 'MX' => 'MEX', 'MY' => 'MYS', 'MZ' => 'MOZ', 'NA' => 'NAM', 'NC' => 'NCL', 'NE' => 'NER', 'NF' => 'NFK', 'NG' => 'NGA', 'NI' => 'NIC', 'NL' => 'NLD', 'NO' => 'NOR', 'NP' => 'NPL',
        'NR' => 'NRU', 'NU' => 'NIU', 'NZ' => 'NZL', 'OM' => 'OMN', 'PA' => 'PAN', 'PE' => 'PER', 'PF' => 'PYF', 'PG' => 'PNG', 'PH' => 'PHL', 'PK' => 'PAK', 'PL' => 'POL', 'PM' => 'SPM', 'PN' => 'PCN', 'PR' => 'PRI',
        'PS' => 'PSE', 'PT' => 'PRT', 'PW' => 'PLW', 'PY' => 'PRY', 'QA' => 'QAT', 'RE' => 'REU', 'RO' => 'ROU', 'RS' => 'SRB', 'RU' => 'RUS', 'RW' => 'RWA', 'SA' => 'SAU', 'SB' => 'SLB', 'SC' => 'SYC', 'SD' => 'SDN',
        'SE' => 'SWE', 'SG' => 'SGP', 'SH' => 'SHN', 'SI' => 'SVN', 'SJ' => 'SJM', 'SK' => 'SVK', 'SL' => 'SLE', 'SM' => 'SMR', 'SN' => 'SEN', 'SO' => 'SOM', 'SR' => 'SUR', 'SS' => 'SSD', 'ST' => 'STP', 'SV' => 'SLV',
        'SX' => 'SXM', 'SY' => 'SYR', 'SZ' => 'SWZ', 'TC' => 'TCA', 'TD' => 'TCD', 'TF' => 'ATF', 'TG' => 'TGO', 'TH' => 'THA', 'TJ' => 'TJK', 'TK' => 'TKL', 'TL' => 'TLS', 'TM' => 'TKM', 'TN' => 'TUN', 'TO' => 'TON',
        'TR' => 'TUR', 'TT' => 'TTO', 'TV' => 'TUV', 'TW' => 'TWN', 'TZ' => 'TZA', 'UA' => 'UKR', 'UG' => 'UGA', 'UM' => 'UMI', 'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB', 'VA' => 'VAT', 'VC' => 'VCT', 'VE' => 'VEN',
        'VG' => 'VGB', 'VI' => 'VIR', 'VN' => 'VNM', 'VU' => 'VUT', 'WF' => 'WLF', 'WS' => 'WSM', 'YE' => 'YEM', 'YT' => 'MYT', 'ZA' => 'ZAF', 'ZM' => 'ZMB', 'ZW' => 'ZWE',
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

    /** Prefijos telefónicos internacionales (UIT E.164). */
    private const PREFIJOS = [
        'AD' => '376', 'AE' => '971', 'AF' => '93', 'AG' => '1', 'AI' => '1', 'AL' => '355', 'AM' => '374', 'AO' => '244', 'AR' => '54', 'AS' => '1', 'AT' => '43', 'AU' => '61', 'AW' => '297', 'AZ' => '994',
        'BA' => '387', 'BB' => '1', 'BD' => '880', 'BE' => '32', 'BF' => '226', 'BG' => '359', 'BH' => '973', 'BI' => '257', 'BJ' => '229', 'BM' => '1', 'BN' => '673', 'BO' => '591', 'BR' => '55', 'BS' => '1', 'BT' => '975', 'BW' => '267', 'BY' => '375', 'BZ' => '501',
        'CA' => '1', 'CD' => '243', 'CF' => '236', 'CG' => '242', 'CH' => '41', 'CI' => '225', 'CK' => '682', 'CL' => '56', 'CM' => '237', 'CN' => '86', 'CO' => '57', 'CR' => '506', 'CU' => '53', 'CV' => '238', 'CW' => '599', 'CY' => '357', 'CZ' => '420',
        'DE' => '49', 'DJ' => '253', 'DK' => '45', 'DM' => '1', 'DO' => '1', 'DZ' => '213', 'EC' => '593', 'EE' => '372', 'EG' => '20', 'ER' => '291', 'ES' => '34', 'ET' => '251', 'FI' => '358', 'FJ' => '679', 'FM' => '691', 'FO' => '298', 'FR' => '33',
        'GA' => '241', 'GB' => '44', 'GD' => '1', 'GE' => '995', 'GF' => '594', 'GH' => '233', 'GI' => '350', 'GL' => '299', 'GM' => '220', 'GN' => '224', 'GP' => '590', 'GQ' => '240', 'GR' => '30', 'GT' => '502', 'GU' => '1', 'GW' => '245', 'GY' => '592',
        'HK' => '852', 'HN' => '504', 'HR' => '385', 'HT' => '509', 'HU' => '36', 'ID' => '62', 'IE' => '353', 'IL' => '972', 'IN' => '91', 'IQ' => '964', 'IR' => '98', 'IS' => '354', 'IT' => '39', 'JM' => '1', 'JO' => '962', 'JP' => '81',
        'KE' => '254', 'KG' => '996', 'KH' => '855', 'KI' => '686', 'KM' => '269', 'KN' => '1', 'KP' => '850', 'KR' => '82', 'KW' => '965', 'KY' => '1', 'KZ' => '7', 'LA' => '856', 'LB' => '961', 'LC' => '1', 'LI' => '423', 'LK' => '94', 'LR' => '231', 'LS' => '266', 'LT' => '370', 'LU' => '352', 'LV' => '371', 'LY' => '218',
        'MA' => '212', 'MC' => '377', 'MD' => '373', 'ME' => '382', 'MG' => '261', 'MH' => '692', 'MK' => '389', 'ML' => '223', 'MM' => '95', 'MN' => '976', 'MO' => '853', 'MQ' => '596', 'MR' => '222', 'MS' => '1', 'MT' => '356', 'MU' => '230', 'MV' => '960', 'MW' => '265', 'MX' => '52', 'MY' => '60', 'MZ' => '258',
        'NA' => '264', 'NC' => '687', 'NE' => '227', 'NG' => '234', 'NI' => '505', 'NL' => '31', 'NO' => '47', 'NP' => '977', 'NR' => '674', 'NZ' => '64', 'OM' => '968', 'PA' => '507', 'PE' => '51', 'PF' => '689', 'PG' => '675', 'PH' => '63', 'PK' => '92', 'PL' => '48', 'PR' => '1', 'PS' => '970', 'PT' => '351', 'PW' => '680', 'PY' => '595', 'QA' => '974',
        'RE' => '262', 'RO' => '40', 'RS' => '381', 'RU' => '7', 'RW' => '250', 'SA' => '966', 'SB' => '677', 'SC' => '248', 'SD' => '249', 'SE' => '46', 'SG' => '65', 'SI' => '386', 'SK' => '421', 'SL' => '232', 'SM' => '378', 'SN' => '221', 'SO' => '252', 'SR' => '597', 'SS' => '211', 'ST' => '239', 'SV' => '503', 'SX' => '1', 'SY' => '963', 'SZ' => '268',
        'TC' => '1', 'TD' => '235', 'TG' => '228', 'TH' => '66', 'TJ' => '992', 'TL' => '670', 'TM' => '993', 'TN' => '216', 'TO' => '676', 'TR' => '90', 'TT' => '1', 'TV' => '688', 'TW' => '886', 'TZ' => '255', 'UA' => '380', 'UG' => '256', 'US' => '1', 'UY' => '598', 'UZ' => '998',
        'VA' => '39', 'VC' => '1', 'VE' => '58', 'VG' => '1', 'VI' => '1', 'VN' => '84', 'VU' => '678', 'WS' => '685', 'YE' => '967', 'ZA' => '27', 'ZM' => '260', 'ZW' => '263',
    ];

    private const PAQUETES = ['MX' => PaquetePais::Mexico, 'ES' => PaquetePais::Espana, 'US' => PaquetePais::EstadosUnidos];

    /** Lo que se escribe a mano y no es el nombre de ningún idioma. */
    private const OTROS_NOMBRES = ['usa' => 'US', 'eua' => 'US', 'eeuu' => 'US', 'ee uu' => 'US', 'uk' => 'GB', 'estados unidos de america' => 'US', 'estados unidos mexicanos' => 'MX', 'republica mexicana' => 'MX'];

    /** @var array<string, string>|null */
    private static ?array $porNombre = null;

    /**
     * El código de un país escrito a mano: «MX», «Mx», «México» y «Messico» son el mismo.
     */
    public static function codigoDe(string $texto): ?string
    {
        $limpio = self::normalizar($texto);
        if (strlen($limpio) === 2 && in_array(strtoupper($limpio), self::CODIGOS, true)) {
            return strtoupper($limpio);
        }
        if (self::$porNombre === null) {
            self::$porNombre = self::OTROS_NOMBRES;
            foreach ((array) config('alojaflow.idiomas') as $idioma) {
                foreach (self::CODIGOS as $codigo) {
                    self::$porNombre[self::normalizar((string) Locale::getDisplayRegion('und-'.$codigo, (string) $idioma))] ??= $codigo;
                }
            }
        }

        return self::$porNombre[$limpio] ?? null;
    }

    /**
     * El código de tres letras de un país escrito a mano.
     */
    public static function alfa3De(?string $texto): ?string
    {
        $limpio = strtoupper(trim((string) $texto));
        if (preg_match('/^[A-Z]{3}$/', $limpio) === 1 && in_array($limpio, self::ALFA3, true)) {
            return $limpio;
        }
        $codigo = $limpio === '' ? null : self::codigoDe((string) $texto);

        return $codigo === null ? null : (self::ALFA3[$codigo] ?? null);
    }

    public static function nombre(string $codigo): string
    {
        return (string) Locale::getDisplayRegion('und-'.$codigo, app()->getLocale());
    }

    private static function normalizar(string $texto): string
    {
        $sinAcentos = (string) preg_replace('/\p{Mn}/u', '', (string) Normalizer::normalize(trim($texto), Normalizer::FORM_D));

        return mb_strtolower(trim((string) preg_replace('/[.\s]+/u', ' ', $sinAcentos)));
    }

    public static function prefijoTelefonico(string $pais): ?string
    {
        return self::PREFIJOS[strtoupper($pais)] ?? null;
    }


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
