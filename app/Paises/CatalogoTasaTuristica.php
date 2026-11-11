<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * Las tasas turísticas por persona y noche de España que se cotejaron con su ley (D-101). Solo Cataluña y las Illes Balears: las de los municipios gallegos y las
 * forales vascas cambian por ordenanza y solo se encontraron en fuentes secundarias, y se dan de alta a mano. Revisado el 2026-11-05.
 */
final class CatalogoTasaTuristica
{
    private const FECHA = '2026-11-05';

    private const FUENTE_CATALUNA = 'atc.gencat.cat/es/tributs/ieet/quota-tributaria';

    private const LEY_CATALUNA = 'Ley 5/2017 de Cataluña, arts. 27, 33 y 34, según la Ley 2/2026 (BOE-A-2026-6642)';

    private const FUENTE_BALEARES = 'boe.es, BOE-A-2016-4175, texto consolidado del 17/05/2025';

    private const LEY_BALEARES = 'Ley 2/2016 de las Illes Balears, arts. 5 y 13';

    /**
     * Barcelona: la tarifa del art. 34.1 más el recargo municipal de cada periodo, que la cuota suma (art. 35.2).
     *
     * @var array<string, array{0: int, 1: int, 2: int}>
     */
    private const BARCELONA = [
        'hotel5' => [1200, 1300, 1400],
        'hotel4' => [840, 980, 1040],
        'vivienda' => [950, 1050, 1150],
        'albergue' => [600, 700, 800],
        'otros' => [700, 800, 900],
    ];

    /** @var array<string, array{0: int, 1: int}> */
    private const RESTO_CATALUNA = [
        'hotel5' => [450, 600],
        'hotel4' => [180, 240],
        'vivienda' => [175, 250],
        'albergue' => [80, 100],
        'otros' => [90, 120],
    ];

    /** @var array<string, int> */
    private const BALEARES = [
        'balear_hotel5' => 400,
        'balear_hotel4' => 300,
        'balear_hotel3' => 200,
        'balear_apartamento4' => 400,
        'balear_apartamento3s' => 300,
        'balear_apartamento3' => 200,
        'vivienda' => 200,
        'balear_rural' => 200,
        'balear_hostal' => 100,
        'albergue' => 100,
        'otros' => 200,
    ];

    /**
     * @return list<SugerenciaImpuesto>
     */
    public static function todas(): array
    {
        $lista = [];
        foreach (self::BARCELONA as $tipo => [$primero, $segundo, $tercero]) {
            $lista[] = self::cataluna(__('autoridad.tasa.lugares.barcelona').' · '.self::tipo($tipo), [
                ['importe' => $primero, 'desde' => '2026-04-01', 'hasta' => '2027-03-31'],
                ['importe' => $segundo, 'desde' => '2027-04-01', 'hasta' => '2028-03-31'],
                ['importe' => $tercero, 'desde' => '2028-04-01', 'hasta' => null],
            ]);
        }
        foreach (self::RESTO_CATALUNA as $tipo => [$primero, $segundo]) {
            $lista[] = self::cataluna(__('autoridad.tasa.lugares.cataluna').' · '.self::tipo($tipo), [
                ['importe' => $primero, 'desde' => '2026-04-01', 'hasta' => '2027-03-31'],
                ['importe' => $segundo, 'desde' => '2027-04-01', 'hasta' => null],
            ]);
        }
        foreach (self::BALEARES as $tipo => $importe) {
            $lista[] = new SugerenciaImpuesto(
                codigo: 'ITS',
                region: __('autoridad.tasa.lugares.baleares').' · '.self::tipo($tipo),
                tasa: 0.0,
                tasaPlataforma: null,
                ley: self::LEY_BALEARES,
                fuente: self::FUENTE_BALEARES,
                fecha: self::FECHA,
                verificada: true,
                tramos: [['importe' => $importe, 'desde' => null, 'hasta' => null]],
                edadMinima: 16,
                temporadaBaja: ['desde' => '11-01', 'hasta' => '04-30', 'descuento' => 75.0],
                diasLargos: ['desde' => 9, 'descuento' => 50.0],
            );
        }

        return $lista;
    }

    /**
     * @param  list<array{importe: int, desde: string|null, hasta: string|null}>  $tramos
     */
    private static function cataluna(string $lugar, array $tramos): SugerenciaImpuesto
    {
        return new SugerenciaImpuesto(
            codigo: 'IEET',
            region: $lugar,
            tasa: 0.0,
            tasaPlataforma: null,
            ley: self::LEY_CATALUNA,
            fuente: self::FUENTE_CATALUNA,
            fecha: self::FECHA,
            verificada: true,
            tramos: $tramos,
            topeNoches: 7,
            edadMinima: 17,
            fijarAlReservar: true,
        );
    }

    private static function tipo(string $tipo): string
    {
        return __('autoridad.tasa.tipos.'.$tipo);
    }
}
