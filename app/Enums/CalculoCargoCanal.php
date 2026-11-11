<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se calcula un cargo de un canal (D-149).
 */
enum CalculoCargoCanal: string
{
    use ConEtiqueta;

    case Porcentaje = 'porcentaje';
    case FijoReserva = 'fijo_reserva';
    case FijoNoche = 'fijo_noche';
}
