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

    /**
     * Se calcula con el reloj, no con un evento de la reserva.
     */
    public function esDeTiempo(): bool
    {
        return in_array($this, [self::AntesLlegada, self::Llegada, self::Salida, self::DespuesSalida], true);
    }

    public function usaDias(): bool
    {
        return in_array($this, [self::AntesLlegada, self::DespuesSalida], true);
    }
}
