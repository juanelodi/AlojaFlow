<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoContacto: string
{
    use ConEtiqueta;

    case Telefono = 'telefono';
    case Correo = 'correo';
    case Fax = 'fax';
}
