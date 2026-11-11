<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Pagos;

/**
 * Lo que devuelve una pasarela al abrir un cobro: su referencia y a dónde mandar al huésped.
 */
final readonly class InicioPago
{
    /**
     * @param  array<string, mixed>  $datos  lo que hace falta guardar en el cobro, además de a dónde mandar
     */
    public function __construct(public string $referencia, public string $url, public array $datos = [])
    {
    }
}
