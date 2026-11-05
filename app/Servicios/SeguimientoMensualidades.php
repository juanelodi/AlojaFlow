<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Models\CuotaReserva;
use App\Models\Reserva;
use App\Reservas\Mensualidades;

/**
 * Lo que deben las estancias largas: lo vencido y lo que viene. AlojaFlow recuerda; cobrar lo decide una persona.
 */
final class SeguimientoMensualidades
{
    public const DIAS_PROXIMAS = 30;

    private const RESERVAS_MAXIMAS = 300;

    /**
     * @param  list<int>  $propiedades
     * @return array{vencidas: list<array{reserva: Reserva, cuota: CuotaReserva, falta: int, whatsapp: string}>, proximas: list<array{reserva: Reserva, cuota: CuotaReserva, falta: int, whatsapp: string}>}
     */
    public static function resumen(array $propiedades): array
    {
        $reservas = Operacion::reservas($propiedades)->with('cuotas')->whereHas('cuotas')->whereColumn('pagado', '<', 'total')->orderBy('llegada')->limit(self::RESERVAS_MAXIMAS)->get();
        $vencidas = [];
        $proximas = [];
        foreach ($reservas as $reserva) {
            $hoy = $reserva->propiedad->hoy();
            $limite = $hoy->addDays(self::DIAS_PROXIMAS)->format('Y-m-d');
            foreach (Mensualidades::de($reserva)['cuotas'] as $fila) {
                $vence = $fila['cuota']->vence_en->format('Y-m-d');
                if ($fila['estado'] === 'pagada' || $vence > $limite) {
                    continue;
                }
                $falta = $fila['cuota']->importe - $fila['pagado'];
                $datos = ['reserva' => $reserva, 'cuota' => $fila['cuota'], 'falta' => $falta, 'whatsapp' => self::whatsapp($reserva, $fila['cuota'], $falta)];
                if ($vence < $hoy->format('Y-m-d')) {
                    $vencidas[] = $datos;
                } else {
                    $proximas[] = $datos;
                }
            }
        }
        $porFecha = fn (array $a, array $b): int => $a['cuota']->vence_en <=> $b['cuota']->vence_en;
        usort($vencidas, $porFecha);
        usort($proximas, $porFecha);

        return ['vencidas' => $vencidas, 'proximas' => $proximas];
    }

    private static function whatsapp(Reserva $reserva, CuotaReserva $cuota, int $falta): string
    {
        return VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($reserva), Mensualidades::recordatorio($reserva, $cuota, $falta), $reserva->propiedad->pais);
    }
}
