<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Tables;

use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Tarifa;
use App\Support\Formato;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TarifasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad')->withCount(['temporadas', 'unidades']))
            ->columns([
                TextColumn::make('nombre')->label(__('ui.tarifas.nombre'))->searchable()->sortable(),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(TarifaResource::propiedadesGestionables()) > 1),
                TextColumn::make('precio_noche')->label(__('ui.tarifas.precio_base'))->alignEnd()->sortable()
                    ->formatStateUsing(fn (Tarifa $record): string => Formato::dinero($record->precio_noche, $record->propiedad->moneda)),
                TextColumn::make('temporadas_count')->label(__('ui.tarifas.temporadas'))->alignEnd(),
                TextColumn::make('unidades_count')->label(__('ui.unidades.modelos'))->alignEnd(),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->defaultSort('nombre')
            ->emptyStateHeading(__('ui.tarifas.vacio'))
            ->recordActions([EditAction::make()]);
    }
}
