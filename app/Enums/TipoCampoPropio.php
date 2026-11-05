<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
