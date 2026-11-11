<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
