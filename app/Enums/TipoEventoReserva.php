<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoEventoReserva: string
{
    use ConEtiqueta;

    case Creada = 'creada';
    case Modificada = 'modificada';
    case Cancelada = 'cancelada';
    case Eliminada = 'eliminada';
    case Reactivada = 'reactivada';
}
