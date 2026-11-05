<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Camping;

use App\Enums\TipoLugar;
use App\Models\Unidad;

/**
 * Lo que el huésped pide de un lugar en el motor (D-110). Un lugar que no dice su largo no se descarta: mejor preguntarlo que perder la reserva.
 */
final readonly class FiltroLugares
{
    private const CASILLAS = ['luz', 'agua', 'drenaje', 'mascotas', 'accesible'];

    /**
     * @param  list<string>  $casillas  lo que tiene que tener
     */
    public function __construct(public ?TipoLugar $tipo = null, public array $casillas = [], public ?float $largo = null)
    {
    }

    /**
     * Lo que venga de una dirección pública: lo que no se entiende, no filtra.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function desde(array $datos): self
    {
        $largo = $datos['largo'] ?? null;

        return new self(
            is_string($datos['tipo_lugar'] ?? null) ? TipoLugar::tryFrom($datos['tipo_lugar']) : null,
            array_values(array_filter(self::CASILLAS, fn (string $casilla): bool => ! empty($datos['con_'.$casilla]))),
            is_numeric($largo) && (float) $largo > 0 ? (float) $largo : null,
        );
    }

    public function cumple(Unidad $unidad): bool
    {
        if ($this->tipo !== null && $unidad->atributo('tipo') !== $this->tipo->value) {
            return false;
        }
        foreach ($this->casillas as $casilla) {
            if (! $unidad->atributo($casilla)) {
                return false;
            }
        }
        $maximo = $unidad->atributo('largo_maximo');

        return $this->largo === null || ! is_numeric($maximo) || $this->largo <= (float) $maximo;
    }

    /**
     * Para que los enlaces conserven lo que pidió.
     *
     * @return array<string, string|int|float>
     */
    public function parametros(): array
    {
        $parametros = [];
        if ($this->tipo !== null) {
            $parametros['tipo_lugar'] = $this->tipo->value;
        }
        foreach ($this->casillas as $casilla) {
            $parametros['con_'.$casilla] = 1;
        }
        if ($this->largo !== null) {
            $parametros['largo'] = $this->largo;
        }

        return $parametros;
    }
}
