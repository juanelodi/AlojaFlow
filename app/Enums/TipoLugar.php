<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Para qué es un lugar de un camping.
 */
enum TipoLugar: string
{
    use ConEtiqueta;

    case Tienda = 'tienda';
    case Vehiculo = 'vehiculo';
    case Cabana = 'cabana';
    case Glamping = 'glamping';
    case Otro = 'otro';
}
