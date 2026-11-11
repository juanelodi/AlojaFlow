<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Motor;

use App\Models\Tarifa;
use App\Models\Unidad;
use App\Reservas\Presupuesto;

/**
 * Lo que se puede reservar para unas fechas: una tarifa con unidades libres y su precio.
 */
final readonly class Oferta
{
    /**
     * @param  list<string>  $etiquetas  lo que tienen todos sus lugares
     * @param  string|null  $politica  las condiciones de esta tarifa, si tiene las suyas
     * @param  string|null  $politicaNombre  cómo se llaman
     * @param  float  $anticipo  el porcentaje que se pide al reservar
     * @param  Unidad|null  $unidad  la que le tocaría
     * @param  int  $cantidad  cuántas unidades pide (D-158); el presupuesto ya es de todas
     */
    public function __construct(
        public Tarifa $tarifa,
        public int $libres,
        public int $capacidad,
        public Presupuesto $presupuesto,
        public int $estanciaMinima,
        public bool $cumpleEstancia,
        public array $etiquetas = [],
        public ?string $politica = null,
        public ?string $politicaNombre = null,
        public float $anticipo = 0.0,
        public ?Unidad $unidad = null,
        public int $cantidad = 1,
    ) {
    }
}
