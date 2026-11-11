<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
