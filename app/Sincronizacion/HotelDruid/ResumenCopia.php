<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Lo que se supo de una copia al leerla, sin sus filas.
 */
final class ResumenCopia
{
    /**
     * @param  string|null  $fechaCopia  cuándo se hizo, en la hora local de la principal (`AAAA-MM-DD HH:MM:SS`)
     * @param  array<string, int>  $filas  filas leídas de cada tabla conservada, con el nombre que trae en la copia
     * @param  list<int>  $anios  los años que tiene la principal
     * @param  array<string, int>  $incidencias  lo que se toleró al leer, contado
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
