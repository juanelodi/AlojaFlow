<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\ConceptoCargo;
use App\Models\Unidad;
use App\Support\Formato;
use Carbon\CarbonImmutable;

/**
 * Cuándo se cobra un cargo del catálogo (D-121): en qué noches, cada cuántas, en qué fechas y con qué tarifas o unidades. Sin reglas, siempre.
 */
final class ReglasCargo
{
    public static function tieneReglas(ConceptoCargo $concepto): bool
    {
        return ! empty($concepto->dias_semana) || (int) $concepto->cada_noches > 1 || $concepto->desde !== null || $concepto->hasta !== null
            || $concepto->noches_minimas !== null || ! empty($concepto->solo_tarifas) || ! empty($concepto->solo_unidades);
    }

    public static function aplica(ConceptoCargo $concepto, Unidad $unidad, ?int $tarifaId, CarbonImmutable $llegada, CarbonImmutable $salida): bool
    {
        return ($concepto->noches_minimas === null || self::noches($llegada, $salida) >= $concepto->noches_minimas)
            && (empty($concepto->solo_unidades) || in_array($unidad->id, $concepto->solo_unidades, true))
            && (empty($concepto->solo_tarifas) || in_array($tarifaId, $concepto->solo_tarifas, true))
            // Fechas sin zona: se comparan como texto. La última noche es la anterior a la salida.
            && ($concepto->desde === null || $salida->format('Y-m-d') > $concepto->desde->format('Y-m-d'))
            && ($concepto->hasta === null || $llegada->format('Y-m-d') <= $concepto->hasta->format('Y-m-d'))
            && (! $concepto->base->esPorNoche() || self::nochesQueCuentan($concepto, $llegada, $salida) > 0);
    }

    /**
     * Cuántas veces cobra un cargo por noche: las noches que caen en sus días y fechas, entre «cada cuántas».
     */
    public static function nochesQueCuentan(ConceptoCargo $concepto, CarbonImmutable $llegada, CarbonImmutable $salida): int
    {
        $total = self::noches($llegada, $salida);
        $cuentan = $total;
        if (! empty($concepto->dias_semana) || $concepto->desde !== null || $concepto->hasta !== null) {
            $desde = $concepto->desde?->format('Y-m-d');
            $hasta = $concepto->hasta?->format('Y-m-d');
            $cuentan = 0;
            // El recorrido es por noche: sin este tope, unas fechas absurdas en un formulario ocupan el servidor.
            for ($i = 0; $i < min($total, GestorReservas::NOCHES_MAXIMAS); $i++) {
                $noche = $llegada->addDays($i);
                $texto = $noche->format('Y-m-d');
                if ((empty($concepto->dias_semana) || in_array($noche->dayOfWeekIso, $concepto->dias_semana, true)) && ($desde === null || $texto >= $desde) && ($hasta === null || $texto <= $hasta)) {
                    $cuentan++;
                }
            }
        }

        return intdiv($cuentan, max(1, (int) $concepto->cada_noches));
    }

    /**
     * Por las fechas sin su zona: con horario de verano, dos medianoches no siempre están a 24 horas.
     */
    private static function noches(CarbonImmutable $llegada, CarbonImmutable $salida): int
    {
        return max(0, (int) CarbonImmutable::parse($llegada->format('Y-m-d'))->diffInDays(CarbonImmutable::parse($salida->format('Y-m-d'))));
    }

    /**
     * «lun, mar · cada 3 noches», para quien va a pagarlo.
     */
    public static function resumen(ConceptoCargo $concepto): string
    {
        if (! $concepto->base->esPorNoche()) {
            return '';
        }
        $partes = [];
        if (! empty($concepto->dias_semana)) {
            $partes[] = Formato::diasCortos($concepto->dias_semana);
        }
        if ((int) $concepto->cada_noches > 1) {
            $partes[] = __('ui.cargos.cada_noches_resumen', ['noches' => $concepto->cada_noches]);
        }

        return implode(' · ', $partes);
    }

    /**
     * Cómo se calcula, con lo anterior si lo tiene.
     */
    public static function etiqueta(ConceptoCargo $concepto): string
    {
        $resumen = self::resumen($concepto);

        return $concepto->base->etiqueta().($resumen === '' ? '' : ' · '.$resumen);
    }
}
