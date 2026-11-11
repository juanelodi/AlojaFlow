<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Con qué llega el huésped a un camping.
 */
enum TipoEquipo: string
{
    use ConEtiqueta;

    case Tienda = 'tienda';
    case Camper = 'camper';
    case Remolque = 'remolque';
    case CasaRodante = 'casa_rodante';
    case Otro = 'otro';
}
