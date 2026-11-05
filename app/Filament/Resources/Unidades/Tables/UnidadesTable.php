<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades\Tables;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Propiedad;
use App\Models\Unidad;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UnidadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'tarifa']))
            ->columns([
                TextColumn::make('nombre')->label(__('ui.unidades.nombre'))->searchable()->sortable(),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(UnidadResource::propiedadesVisibles()) > 1),
                TextColumn::make('capacidad')->label(__('ui.unidades.capacidad'))->alignEnd()->formatStateUsing(fn (Unidad $record): string => $record->capacidad.($record->camas_extra > 0 ? ' + '.$record->camas_extra : ''))->sortable(),
                TextColumn::make('tarifa.nombre')->label(__('ui.unidades.tarifa'))->placeholder('—'),
                TextColumn::make('edificio')->label(__('ui.unidades.edificio'))->placeholder('—')->toggleable(),
                TextColumn::make('piso')->label(__('ui.unidades.piso'))->placeholder('—')->toggleable(),
                TextColumn::make('orden')->label(__('ui.unidades.orden'))->alignEnd()->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activa')->label(__('ui.unidades.activa'))->boolean(),
            ])
            ->defaultSort('orden')
            ->filters([
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))
                    ->options(fn (): array => Propiedad::query()->whereIn('id', UnidadResource::propiedadesVisibles())->orderBy('nombre')->pluck('nombre', 'id')->all()),
            ])
            ->emptyStateHeading(__('ui.unidades.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Unidad $record): bool => UnidadResource::canEdit($record))]);
    }
}
