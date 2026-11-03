<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Cuentas de una proyección, para el registro de la ejecución.
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
