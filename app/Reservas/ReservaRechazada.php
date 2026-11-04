<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use RuntimeException;

/**
 * Una regla de negocio impide el cambio. El mensaje ya viene traducido; $campo dice en qué campo
 * del formulario se muestra.
 */
class ReservaRechazada extends RuntimeException
{
    public function __construct(string $mensaje, public readonly string $campo = 'unidad_id')
    {
        parent::__construct($mensaje);
    }
}
