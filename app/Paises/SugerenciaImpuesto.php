<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

/**
 * Una tasa que se propone, con de dónde salió. Es un punto de partida, siempre editable: las leyes cambian y las fuentes no coinciden.
 */
final readonly class SugerenciaImpuesto
{
    /**
     * @param  string  $codigo  corto, para el código del impuesto (ISH)
     * @param  string  $region  estado o provincia
     * @param  float  $tasa  porcentaje
     * @param  float|null  $tasaPlataforma  cuando cobra una plataforma digital o un intermediario, si es distinta
     * @param  string  $ley  dónde comprobarla
     * @param  bool  $verificada  se cotejó contra la ley; si no, solo viene de una fuente secundaria
     * @param  list<array{importe: int, desde: string|null, hasta: string|null}>  $tramos  importes por persona y noche, en centavos, si no es un porcentaje
     * @param  array{desde: string, hasta: string, descuento: float}|null  $temporadaBaja
     * @param  array{desde: int, descuento: float}|null  $diasLargos
     */
    public function __construct(
        public string $codigo,
        public string $region,
        public float $tasa,
        public ?float $tasaPlataforma,
        public string $ley,
        public string $fuente,
        public string $fecha,
        public bool $verificada,
        public array $tramos = [],
        public ?int $topeNoches = null,
        public ?int $edadMinima = null,
        public bool $fijarAlReservar = false,
        public ?array $temporadaBaja = null,
        public ?array $diasLargos = null,
    ) {
    }
}
