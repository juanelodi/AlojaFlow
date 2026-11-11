<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * El impuesto sobre hospedaje de cada entidad de México (D-082). **Son tasas orientativas de una fuente secundaria:** solo la Ciudad de
 * México y Quintana Roo se cotejaron con su ley, y para varias entidades las fuentes no coinciden. Por eso la pantalla enseña de dónde
 * sale cada una y deja cambiarla. Revisado el 2026-11-05.
 */
final class CatalogoIsh
{
    private const FUENTE = 'elcontribuyente.mx/impuesto-sobre-hospedaje';

    private const FUENTE_VERIFICADA = 'praxiumconsultores.com/blog/impuesto-sobre-hospedaje-por-estado-2026';

    /** Nombre => [tasa, tasa con plataforma, ley donde comprobarla]. */
    private const TASAS = [
        'Aguascalientes' => [3.0, null, 'Ley de Hacienda del Estado de Aguascalientes'],
        'Baja California' => [5.0, null, 'Ley de Hacienda del Estado de Baja California'],
        'Baja California Sur' => [4.0, null, 'Ley de Hacienda del Estado de Baja California Sur'],
        'Campeche' => [2.0, null, 'Ley de Hacienda del Estado de Campeche'],
        'Chiapas' => [2.0, null, 'Código de la Hacienda Pública del Estado de Chiapas'],
        'Chihuahua' => [4.0, null, 'Ley de Hacienda del Estado de Chihuahua'],
        'Ciudad de México' => [3.5, 5.0, 'Código Fiscal de la Ciudad de México, arts. 162 a 164'],
        'Coahuila' => [3.0, null, 'Ley de Hacienda para el Estado de Coahuila de Zaragoza'],
        'Colima' => [3.0, null, 'Ley de Hacienda del Estado de Colima'],
        'Durango' => [3.0, null, 'Ley de Hacienda del Estado de Durango'],
        'Estado de México' => [4.0, null, 'Código Financiero del Estado de México y Municipios'],
        'Guanajuato' => [4.0, null, 'Ley de Hacienda para el Estado de Guanajuato'],
        'Guerrero' => [4.0, 5.0, 'Ley de Hacienda del Estado de Guerrero'],
        'Hidalgo' => [2.5, null, 'Ley de Hacienda del Estado de Hidalgo'],
        'Jalisco' => [4.0, null, 'Ley de Hacienda del Estado de Jalisco'],
        'Michoacán' => [3.0, null, 'Ley de Hacienda del Estado de Michoacán de Ocampo'],
        'Morelos' => [3.75, null, 'Ley General de Hacienda del Estado de Morelos'],
        'Nayarit' => [5.0, null, 'Ley de Hacienda del Estado de Nayarit'],
        'Nuevo León' => [3.0, null, 'Ley de Hacienda del Estado de Nuevo León'],
        'Oaxaca' => [3.0, null, 'Ley Estatal de Hacienda de Oaxaca'],
        'Puebla' => [3.0, null, 'Ley de Hacienda del Estado de Puebla'],
        'Querétaro' => [3.5, 5.0, 'Ley de Hacienda del Estado de Querétaro'],
        'Quintana Roo' => [5.0, 6.0, 'Ley del Impuesto al Hospedaje, art. 8'],
        'San Luis Potosí' => [4.0, null, 'Ley de Hacienda para el Estado de San Luis Potosí'],
        'Sinaloa' => [3.0, null, 'Ley de Hacienda del Estado de Sinaloa'],
        'Sonora' => [3.0, null, 'Ley de Hacienda del Estado de Sonora'],
        'Tabasco' => [3.0, null, 'Ley de Hacienda del Estado de Tabasco'],
        'Tamaulipas' => [3.0, null, 'Ley de Hacienda para el Estado de Tamaulipas'],
        'Tlaxcala' => [2.0, null, 'Código Financiero para el Estado de Tlaxcala y sus Municipios'],
        'Veracruz' => [2.0, null, 'Código Financiero para el Estado de Veracruz de Ignacio de la Llave'],
        'Yucatán' => [4.5, null, 'Ley General de Hacienda del Estado de Yucatán'],
        'Zacatecas' => [3.0, null, 'Ley de Hacienda del Estado de Zacatecas'],
    ];

    private const VERIFICADAS = ['Ciudad de México', 'Quintana Roo'];

    /**
     * @return list<SugerenciaImpuesto>
     */
    public static function todas(): array
    {
        $sugerencias = [];
        foreach (self::TASAS as $region => [$tasa, $plataforma, $ley]) {
            $verificada = in_array($region, self::VERIFICADAS, true);
            $sugerencias[] = new SugerenciaImpuesto('ISH', $region, $tasa, $plataforma, $ley, $verificada ? self::FUENTE_VERIFICADA : self::FUENTE, '2026', $verificada);
        }

        return $sugerencias;
    }
}
