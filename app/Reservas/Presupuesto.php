<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

/**
 * Lo que cuesta una estancia, ya desglosado, en la unidad menor de la moneda.
 */
final readonly class Presupuesto
{
    /**
     * @param  list<array{concepto_cargo_id: int|null, nombre: string, cantidad: float, importe: int}>  $cargos
     * @param  list<array{nombre: string, importe: int, incluido: bool}>  $impuestos
     */
    public function __construct(
        public ?Cotizacion $cotizacion,
        public int $alojamiento,
        public int $descuento,
        public array $cargos,
        public array $impuestos,
    ) {
    }

    public function totalCargos(): int
    {
        return array_sum(array_column($this->cargos, 'importe'));
    }

    /**
     * Lo que se suma al precio; los impuestos incluidos solo se informan.
     */
    public function impuestosAdicionales(): int
    {
        $total = 0;
        foreach ($this->impuestos as $impuesto) {
            if (! $impuesto['incluido']) {
                $total += $impuesto['importe'];
            }
        }

        return $total;
    }

    public function impuestosIncluidos(): int
    {
        $total = 0;
        foreach ($this->impuestos as $impuesto) {
            if ($impuesto['incluido']) {
                $total += $impuesto['importe'];
            }
        }

        return $total;
    }

    public function total(): int
    {
        return max(0, $this->alojamiento - $this->descuento + $this->totalCargos() + $this->impuestosAdicionales());
    }
}
