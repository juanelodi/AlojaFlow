<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

enum DisparadorAutomatizacion: string
{
    use ConEtiqueta;

    case ReservaCreada = 'reserva.creada';
    case ReservaConfirmada = 'reserva.confirmada';
    case ReservaModificada = 'reserva.modificada';
    case ReservaCancelada = 'reserva.cancelada';
    case AntesLlegada = 'antes_llegada';
    case Llegada = 'llegada';
    case Salida = 'salida';
    case DespuesSalida = 'despues_salida';
    case AntesCuota = 'antes_cuota';
    case CuotaVencida = 'cuota_vencida';

    /**
     * Se calcula con el reloj, no con un evento de la reserva.
     */
    public function esDeTiempo(): bool
    {
        return in_array($this, [self::AntesLlegada, self::Llegada, self::Salida, self::DespuesSalida, self::AntesCuota, self::CuotaVencida], true);
    }

    public function usaDias(): bool
    {
        return in_array($this, [self::AntesLlegada, self::DespuesSalida, self::AntesCuota, self::CuotaVencida], true);
    }

    /**
     * Se mira cada pago del calendario de cobros, no las fechas de la reserva.
     */
    public function esDeCuota(): bool
    {
        return in_array($this, [self::AntesCuota, self::CuotaVencida], true);
    }
}
