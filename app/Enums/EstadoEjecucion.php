<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

enum EstadoEjecucion: string
{
    use ConEtiqueta;

    case EnCurso = 'en_curso';
    case Correcta = 'correcta';
    case Fallida = 'fallida';
    case Omitida = 'omitida';
}
