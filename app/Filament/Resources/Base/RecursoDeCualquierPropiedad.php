<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
