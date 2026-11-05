<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Base;

use App\Modulos\Modulos;
use App\Seguridad\Acceso;

/**
 * Datos propios de AlojaFlow (tareas, plantillas, calendarios…): también valen para las subordinadas.
 */
abstract class RecursoDeCualquierPropiedad extends RecursoDePropiedad
{
    public static function propiedadesGestionables(): array
    {
        return Modulos::disponible(static::modulo()) ? Acceso::propiedades(Acceso::usuarioActual(), static::permiso()) : [];
    }
}
