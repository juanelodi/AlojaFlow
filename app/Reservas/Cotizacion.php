<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

/**
 * Precio de una estancia según la tarifa de la unidad, en la unidad menor de la moneda.
 */
final readonly class Cotizacion
{
    /**
     * @param  list<int>  $noches  precio de cada noche
     */
    public function __construct(
        public string $tarifa,
        public int $total,
        public array $noches,
    ) {
    }
}
