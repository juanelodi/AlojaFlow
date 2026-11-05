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
    public const NOCHES_SEMANA = 7;

    public const NOCHES_MES = 28;

    public const TEMPORADA = 'temporada';

    /**
     * @param  list<int>  $noches  precio de cada noche
     * @param  int  $estanciaMinima  noches mínimas que pide la tarifa o la temporada de la llegada
     * @param  string|null  $estanciaLarga  si alguna noche se cobró con el precio por semana o por mes
     * @param  int|null  $tarifaId  la tarifa con que se calculó
     */
    public function __construct(
        public string $tarifa,
        public int $total,
        public array $noches,
        public int $estanciaMinima = 1,
        public ?string $estanciaLarga = null,
        public ?int $tarifaId = null,
    ) {
    }

    public function cumpleEstanciaMinima(): bool
    {
        return count($this->noches) >= $this->estanciaMinima;
    }
}
