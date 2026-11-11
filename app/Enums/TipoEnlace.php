<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoEnlace: string
{
    use ConEtiqueta;

    case Ical = 'ical';
    case Llegada = 'llegada';
    case Contrato = 'contrato';
    case Pago = 'pago';
    case Opinion = 'opinion';
    case Grupo = 'grupo';
    case Propietario = 'propietario';
    case MiReserva = 'mi_reserva';
    case Guia = 'guia';
}
