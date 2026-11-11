<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Paises;

use App\Enums\PaquetePais;
use App\Models\Propiedad;

/**
 * El único lugar donde se decide qué paquete usa una propiedad.
 */
final class Paises
{
    public static function de(Propiedad $propiedad): Pais
    {
        return match ($propiedad->paquete_pais) {
            PaquetePais::Mexico => new Mexico(),
            PaquetePais::Espana => new Espana(),
            PaquetePais::EstadosUnidos => new EstadosUnidos(),
            PaquetePais::Generico => new Generico(),
        };
    }
}
