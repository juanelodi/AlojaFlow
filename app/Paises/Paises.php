<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
