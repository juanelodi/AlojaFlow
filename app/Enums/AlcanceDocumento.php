<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * De qué sale un documento propio: de una reserva, o de las de unas fechas (D-123).
 */
enum AlcanceDocumento: string
{
    use ConEtiqueta;

    case Reserva = 'reserva';
    case Periodo = 'periodo';
}
