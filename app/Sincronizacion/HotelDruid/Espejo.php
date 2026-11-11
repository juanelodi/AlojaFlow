<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Generator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Lee del espejo lo que cargó una ejecución, tabla por tabla y en el orden de la copia. Solo lo usa la proyección.
 */
final class Espejo
{
    private const LOTE = 1000;

    public function __construct(private readonly int $ejecucionId)
    {
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    public function filas(string $tabla, ?int $anio = null): Generator
    {
        $consulta = DB::table('hd_filas')->where('ejecucion_id', $this->ejecucionId)->where('tabla', $tabla);
        $consulta = $anio === null ? $consulta->whereNull('anio') : $consulta->where('anio', $anio);

        foreach ($consulta->select(['id', 'datos'])->lazyById(self::LOTE) as $registro) {
            $fila = json_decode(Crypt::decryptString((string) $registro->datos), true);
            if (is_array($fila)) {
                /** @var array<string, string|null> $fila */
                yield $fila;
            }
        }
    }

    /**
     * Los años de la principal, de menor a mayor.
     *
     * @return list<int>
     */
    public function anios(): array
    {
        $anios = [];
        foreach ($this->filas('anni') as $fila) {
            if (ctype_digit((string) ($fila['idanni'] ?? ''))) {
                $anios[] = (int) $fila['idanni'];
            }
        }
        sort($anios);

        return array_values(array_unique($anios));
    }
}
