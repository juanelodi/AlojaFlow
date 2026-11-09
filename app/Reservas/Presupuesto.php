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
     * @param  list<array{nombre: string, importe: int, incluido: bool, canal: bool}>  $impuestos
     */
    public function __construct(
        public ?Cotizacion $cotizacion,
        public int $alojamiento,
        public int $descuento,
        public array $cargos,
        public array $impuestos,
    ) {
    }

    /**
     * Varias unidades de un mismo pedido (D-158): se suman, y los cargos y los impuestos que se llaman igual van juntos. La
     * cotización es la de la primera: la estancia mínima es la misma para todas.
     *
     * @param  non-empty-list<self>  $partes
     */
    public static function sumar(array $partes): self
    {
        $cargos = [];
        $impuestos = [];
        foreach ($partes as $parte) {
            foreach ($parte->cargos as $cargo) {
                $llave = ($cargo['concepto_cargo_id'] ?? 'x').'|'.$cargo['nombre'];
                if (isset($cargos[$llave])) {
                    $cargos[$llave]['cantidad'] += $cargo['cantidad'];
                    $cargos[$llave]['importe'] += $cargo['importe'];
                } else {
                    $cargos[$llave] = $cargo;
                }
            }
            foreach ($parte->impuestos as $impuesto) {
                $llave = $impuesto['nombre'].'|'.(int) $impuesto['incluido'].'|'.(int) $impuesto['canal'];
                if (isset($impuestos[$llave])) {
                    $impuestos[$llave]['importe'] += $impuesto['importe'];
                } else {
                    $impuestos[$llave] = $impuesto;
                }
            }
        }

        return new self(
            $partes[0]->cotizacion,
            array_sum(array_map(fn (self $parte): int => $parte->alojamiento, $partes)),
            array_sum(array_map(fn (self $parte): int => $parte->descuento, $partes)),
            array_values($cargos),
            array_values($impuestos),
        );
    }

    public function totalCargos(): int
    {
        return array_sum(array_column($this->cargos, 'importe'));
    }

    /**
     * Lo que se suma al precio; los incluidos solo se informan, y lo que cobra el portal no se cobra otra vez.
     */
    public function impuestosAdicionales(): int
    {
        $total = 0;
        foreach ($this->impuestos as $impuesto) {
            if (! $impuesto['incluido'] && ! $impuesto['canal']) {
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
