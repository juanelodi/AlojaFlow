<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

enum EstadoReserva: string
{
    use ConEtiqueta;

    case SinConfirmar = 'sin_confirmar';
    case Confirmada = 'confirmada';
    case Cancelada = 'cancelada';
}
