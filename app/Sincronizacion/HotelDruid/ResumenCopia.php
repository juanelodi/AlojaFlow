<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Lo que se sabe de una copia tras leerla entera.
 */
final class ResumenCopia
{
    /**
     * @param  array<string, int>  $filas  filas conservadas por tabla (prenota2026 => 812)
     * @param  list<int>  $anios
     * @param  array<string, int>  $incidencias
     */
    public function __construct(
        public readonly string $version,
        public readonly ?string $fechaCopia,
        public readonly ?string $idioma,
        public readonly bool $inicioSesionActivado,
        public readonly array $filas,
        public readonly array $anios,
        public readonly array $incidencias,
    ) {
    }
}
