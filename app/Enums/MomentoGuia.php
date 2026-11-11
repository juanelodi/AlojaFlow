<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Cuándo le sirve al huésped una parte de la guía.
 */
enum MomentoGuia: string
{
    use ConEtiqueta;

    case Siempre = 'siempre';
    case Antes = 'antes';
    case Durante = 'durante';
    case Despues = 'despues';
}
