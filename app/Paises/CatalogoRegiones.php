<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

use Illuminate\Support\Str;

/**
 * Estados y regiones con su código ISO 3166-2 (D-092). Son nombres propios: no se traducen. Los países que no están aquí escriben su región a mano.
 */
final class CatalogoRegiones
{
    private const REGIONES = [
        'MX' => ['AGU' => 'Aguascalientes', 'BCN' => 'Baja California', 'BCS' => 'Baja California Sur', 'CAM' => 'Campeche', 'CHP' => 'Chiapas', 'CHH' => 'Chihuahua', 'CMX' => 'Ciudad de México', 'COA' => 'Coahuila', 'COL' => 'Colima', 'DUR' => 'Durango', 'MEX' => 'Estado de México', 'GUA' => 'Guanajuato', 'GRO' => 'Guerrero', 'HID' => 'Hidalgo', 'JAL' => 'Jalisco', 'MIC' => 'Michoacán', 'MOR' => 'Morelos', 'NAY' => 'Nayarit', 'NLE' => 'Nuevo León', 'OAX' => 'Oaxaca', 'PUE' => 'Puebla', 'QUE' => 'Querétaro', 'ROO' => 'Quintana Roo', 'SLP' => 'San Luis Potosí', 'SIN' => 'Sinaloa', 'SON' => 'Sonora', 'TAB' => 'Tabasco', 'TAM' => 'Tamaulipas', 'TLA' => 'Tlaxcala', 'VER' => 'Veracruz', 'YUC' => 'Yucatán', 'ZAC' => 'Zacatecas'],
        'US' => ['AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas', 'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware', 'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii', 'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa', 'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine', 'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota', 'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska', 'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico', 'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio', 'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island', 'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas', 'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington', 'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming'],
        'CA' => ['AB' => 'Alberta', 'BC' => 'British Columbia', 'MB' => 'Manitoba', 'NB' => 'New Brunswick', 'NL' => 'Newfoundland and Labrador', 'NS' => 'Nova Scotia', 'NT' => 'Northwest Territories', 'NU' => 'Nunavut', 'ON' => 'Ontario', 'PE' => 'Prince Edward Island', 'QC' => 'Québec', 'SK' => 'Saskatchewan', 'YT' => 'Yukon'],
        'ES' => ['AN' => 'Andalucía', 'AR' => 'Aragón', 'AS' => 'Asturias', 'CN' => 'Canarias', 'CB' => 'Cantabria', 'CL' => 'Castilla y León', 'CM' => 'Castilla-La Mancha', 'CT' => 'Cataluña', 'CE' => 'Ceuta', 'EX' => 'Extremadura', 'GA' => 'Galicia', 'IB' => 'Illes Balears', 'RI' => 'La Rioja', 'MD' => 'Madrid', 'ML' => 'Melilla', 'MC' => 'Murcia', 'NC' => 'Navarra', 'PV' => 'País Vasco', 'VC' => 'Comunitat Valenciana'],
        'FR' => ['ARA' => 'Auvergne-Rhône-Alpes', 'BFC' => 'Bourgogne-Franche-Comté', 'BRE' => 'Bretagne', 'CVL' => 'Centre-Val de Loire', '20R' => 'Corse', 'GES' => 'Grand Est', 'HDF' => 'Hauts-de-France', 'IDF' => 'Île-de-France', 'NOR' => 'Normandie', 'NAQ' => 'Nouvelle-Aquitaine', 'OCC' => 'Occitanie', 'PDL' => 'Pays de la Loire', 'PAC' => 'Provence-Alpes-Côte d’Azur'],
        'IT' => ['65' => 'Abruzzo', '77' => 'Basilicata', '78' => 'Calabria', '72' => 'Campania', '45' => 'Emilia-Romagna', '36' => 'Friuli Venezia Giulia', '62' => 'Lazio', '42' => 'Liguria', '25' => 'Lombardia', '57' => 'Marche', '67' => 'Molise', '21' => 'Piemonte', '75' => 'Puglia', '88' => 'Sardegna', '82' => 'Sicilia', '52' => 'Toscana', '32' => 'Trentino-Alto Adige', '55' => 'Umbria', '23' => 'Valle d’Aosta', '34' => 'Veneto'],
        'DE' => ['BW' => 'Baden-Württemberg', 'BY' => 'Bayern', 'BE' => 'Berlin', 'BB' => 'Brandenburg', 'HB' => 'Bremen', 'HH' => 'Hamburg', 'HE' => 'Hessen', 'MV' => 'Mecklenburg-Vorpommern', 'NI' => 'Niedersachsen', 'NW' => 'Nordrhein-Westfalen', 'RP' => 'Rheinland-Pfalz', 'SL' => 'Saarland', 'SN' => 'Sachsen', 'ST' => 'Sachsen-Anhalt', 'SH' => 'Schleswig-Holstein', 'TH' => 'Thüringen'],
        'PT' => ['01' => 'Aveiro', '02' => 'Beja', '03' => 'Braga', '04' => 'Bragança', '05' => 'Castelo Branco', '06' => 'Coimbra', '07' => 'Évora', '08' => 'Faro', '09' => 'Guarda', '10' => 'Leiria', '11' => 'Lisboa', '12' => 'Portalegre', '13' => 'Porto', '14' => 'Santarém', '15' => 'Setúbal', '16' => 'Viana do Castelo', '17' => 'Vila Real', '18' => 'Viseu', '20' => 'Região Autónoma dos Açores', '30' => 'Região Autónoma da Madeira'],
    ];

    /**
     * @return array<string, string> código ISO completo (MX-ROO) => nombre, por nombre
     */
    public static function de(?string $pais): array
    {
        $pais = strtoupper((string) $pais);
        $regiones = [];
        foreach (self::REGIONES[$pais] ?? [] as $codigo => $nombre) {
            $regiones[$pais.'-'.$codigo] = $nombre;
        }
        uasort($regiones, fn (string $a, string $b): int => strcmp(Str::ascii($a), Str::ascii($b)));

        return $regiones;
    }

    /**
     * El nombre de un código, o lo que se escribió a mano si no se reconoce.
     */
    public static function nombre(?string $subdivision): ?string
    {
        if (blank($subdivision)) {
            return null;
        }

        return self::de(explode('-', (string) $subdivision)[0])[(string) $subdivision] ?? $subdivision;
    }
}
