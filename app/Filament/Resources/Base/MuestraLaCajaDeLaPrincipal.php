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
use Illuminate\Database\Eloquent\Builder;

/**
 * En una subordinada, la caja viene de la principal (D-078): se ve con el permiso de ver datos, pero no se puede cambiar, porque esa
 * propiedad no está entre las que se gestionan aquí.
 */
trait MuestraLaCajaDeLaPrincipal
{
    /**
     * @return list<int>
     */
    public static function propiedadesVisibles(): array
    {
        if (! Modulos::disponible(static::modulo())) {
            return [];
        }
        $usuario = Acceso::usuarioActual();
        $subordinadas = array_diff(Acceso::propiedades($usuario, Acceso::DATOS), Acceso::propiedadesIndependientes($usuario, Acceso::DATOS));

        return array_values(array_unique([...static::propiedadesGestionables(), ...$subordinadas]));
    }

    public static function getEloquentQuery(): Builder
    {
        return static::getModel()::query()->whereIn('propiedad_id', self::propiedadesVisibles());
    }

    public static function canViewAny(): bool
    {
        return self::propiedadesVisibles() !== [];
    }

    public static function canCreate(): bool
    {
        return static::propiedadesGestionables() !== [];
    }
}
