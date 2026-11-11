<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Lo que hizo una proyección: cuántas filas tocó, qué toleró y cuántos eventos dejó. Lo usan los dos proyectores.
 */
final class ResultadoProyeccion
{
    /** @var array<string, int> */
    public array $cuentas = [];

    /** @var array<string, int> */
    public array $incidencias = [];

    public int $eventos = 0;

    public function contar(string $clave, int $cuantos = 1): void
    {
        $this->cuentas[$clave] = ($this->cuentas[$clave] ?? 0) + $cuantos;
    }

    public function incidencia(string $clave): void
    {
        $this->incidencias[$clave] = ($this->incidencias[$clave] ?? 0) + 1;
    }
}
