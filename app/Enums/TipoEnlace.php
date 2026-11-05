<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoEnlace: string
{
    use ConEtiqueta;

    case Ical = 'ical';
    case Llegada = 'llegada';
    case Contrato = 'contrato';
    case Factura = 'factura';
    case Pago = 'pago';
    case Opinion = 'opinion';
}
