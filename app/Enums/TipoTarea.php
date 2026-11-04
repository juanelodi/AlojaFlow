<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
