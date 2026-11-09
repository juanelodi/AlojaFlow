<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

enum AccionAutomatizacion: string
{
    use ConEtiqueta;

    case CorreoHuesped = 'correo_huesped';
    case CorreoFijo = 'correo_fijo';
    case CrearTarea = 'crear_tarea';
    case NotaBitacora = 'nota_bitacora';

    public function esCorreo(): bool
    {
        return $this === self::CorreoHuesped || $this === self::CorreoFijo;
    }
}
