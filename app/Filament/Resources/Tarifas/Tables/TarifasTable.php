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
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'base'])->withCount(['temporadas', 'unidades', 'otrasUnidades']))
            ->columns([
                TextColumn::make('nombre')->label(__('ui.tarifas.nombre'))->searchable()->sortable(),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(TarifaResource::propiedadesGestionables()) > 1),
                TextColumn::make('precio_noche')->label(__('ui.tarifas.precio_base'))->alignEnd()->sortable()->wrap()
                    ->formatStateUsing(fn (Tarifa $record): string => $record->base === null ? Formato::dinero($record->precio_noche, $record->propiedad->moneda) : self::ajuste($record)),
                TextColumn::make('modo')->label(__('ui.tarifas.modo'))->formatStateUsing(fn (Tarifa $record): string => $record->modo->etiqueta())->toggleable(),
                TextColumn::make('temporadas_count')->label(__('ui.tarifas.temporadas'))->alignEnd(),
                TextColumn::make('unidades_count')->label(__('ui.unidades.modelos'))->alignEnd()
                    ->state(fn (Tarifa $record): int => (int) $record->getAttribute('unidades_count') + (int) $record->getAttribute('otras_unidades_count')),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->defaultSort('nombre')
            ->emptyStateHeading(__('ui.tarifas.vacio'))
            ->recordActions([EditAction::make()]);
    }

    /**
     * «Doble: -10 %, + $150.00 por noche».
     */
    private static function ajuste(Tarifa $tarifa): string
    {
        $partes = [];
        if ((float) $tarifa->ajuste_porcentaje !== 0.0) {
            $partes[] = ((float) $tarifa->ajuste_porcentaje > 0 ? '+' : '').Formato::tasa($tarifa->ajuste_porcentaje);
        }
        if ((int) $tarifa->ajuste_noche !== 0) {
            $partes[] = '+ '.Formato::dinero((int) $tarifa->ajuste_noche, $tarifa->propiedad->moneda).' '.__('ui.tarifas.por_noche');
        }

        return __('ui.tarifas.resumen_ajuste', ['tarifa' => (string) $tarifa->base?->nombre, 'ajuste' => $partes === [] ? __('ui.tarifas.sin_ajuste') : implode(', ', $partes)]);
    }
}
