<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Base;

use App\Models\Propiedad;
use App\Seguridad\Acceso;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Catálogo de una propiedad independiente: solo se ve y se cambia lo de las propiedades en las que
 * el usuario tiene el permiso.
 */
abstract class RecursoDePropiedad extends Resource
{
    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    /**
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Acceso::propiedadesIndependientes(Acceso::usuarioActual(), static::permiso());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('propiedad_id', static::propiedadesGestionables());
    }

    public static function canViewAny(): bool
    {
        return static::propiedadesGestionables() !== [];
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return in_array((int) $record->getAttribute('propiedad_id'), static::propiedadesGestionables(), true);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function campoPropiedad(): Select
    {
        return Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
            ->options(fn (): array => Propiedad::query()->whereIn('id', static::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
            ->default(function (): ?int {
                $permitidas = static::propiedadesGestionables();

                return count($permitidas) === 1 ? $permitidas[0] : null;
            })
            ->disabledOn('edit');
    }

    public static function columnaPropiedad(): TextColumn
    {
        return TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
            ->visible(fn (): bool => count(static::propiedadesGestionables()) > 1);
    }
}
