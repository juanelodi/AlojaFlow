<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que dice haber pagado alguien no es un pago hasta que el alojamiento lo confirma.
 */
enum EstadoComprobante: string
{
    use ConEtiqueta;

    case PorConfirmar = 'por_confirmar';
    case Confirmado = 'confirmado';
    case Rechazado = 'rechazado';
}
