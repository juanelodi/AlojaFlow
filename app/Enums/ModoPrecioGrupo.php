<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Tarifa: el precio normal de cada unidad. Total: un importe que se reparte por noches. Por capacidad: un precio por noche según cuántos caben.
 */
enum ModoPrecioGrupo: string
{
    use ConEtiqueta;

    case Tarifa = 'tarifa';
    case Total = 'total';
    case PorCapacidad = 'por_capacidad';
}
