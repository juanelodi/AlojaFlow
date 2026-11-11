<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Un estado de cuenta se revisa, se le manda al dueño y se le paga.
 */
enum FaseEstadoCuenta: string
{
    use ConEtiqueta;

    case Borrador = 'borrador';
    case Enviado = 'enviado';
    case Pagado = 'pagado';
}
