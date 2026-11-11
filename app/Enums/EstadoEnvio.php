<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum EstadoEnvio: string
{
    use ConEtiqueta;

    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Fallido = 'fallido';
    case Omitido = 'omitido';
    case Manual = 'manual';
}
