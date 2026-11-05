<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * De qué es un campo propio: de un huésped o de una reserva (D-122).
 */
enum EntidadCampo: string
{
    use ConEtiqueta;

    case Huesped = 'huesped';
    case Reserva = 'reserva';
}
