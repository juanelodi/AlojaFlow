<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum EstadoActualizacion: string
{
    use ConEtiqueta;

    case Preparada = 'preparada';
    case Aplicando = 'aplicando';
    case ArchivosAplicados = 'archivos_aplicados';
    case Completada = 'completada';
    case Fallida = 'fallida';
    case Revertida = 'revertida';

    public function enCurso(): bool
    {
        return in_array($this, [self::Aplicando, self::ArchivosAplicados], true);
    }
}
