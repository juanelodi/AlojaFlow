<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué guarda un campo propio (D-122).
 */
enum TipoCampoPropio: string
{
    use ConEtiqueta;

    case Texto = 'texto';
    case SiNo = 'si_no';
}
