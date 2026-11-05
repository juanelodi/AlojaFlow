<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Los motivos que acepta el SAT. El 01 necesita ya emitida la factura que sustituye a la cancelada.
 */
enum MotivoCancelacionCfdi: string
{
    use ConEtiqueta;

    case ConErroresConRelacion = '01';
    case ConErroresSinRelacion = '02';
    case NoSeRealizo = '03';
    case EnFacturaGlobal = '04';
}
