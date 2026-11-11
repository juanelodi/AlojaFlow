<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Dinero;

/**
 * Lo que de verdad se recibió cuando se pagó en la segunda moneda (D-157): el importe en su unidad menor y el tipo de cambio,
 * como texto decimal para no perder cifras.
 */
final readonly class PagoRecibido
{
    public function __construct(public string $moneda, public int $importe, public string $tipoCambio)
    {
    }
}
