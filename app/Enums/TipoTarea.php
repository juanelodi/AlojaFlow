<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoTarea: string
{
    use ConEtiqueta;

    case LimpiezaSalida = 'limpieza_salida';
    case LimpiezaEstancia = 'limpieza_estancia';
    case LimpiezaProfunda = 'limpieza_profunda';
    case Inspeccion = 'inspeccion';
    case Mantenimiento = 'mantenimiento';
    case Otra = 'otra';

    public function esLimpieza(): bool
    {
        return in_array($this, [self::LimpiezaSalida, self::LimpiezaEstancia, self::LimpiezaProfunda], true);
    }
}
