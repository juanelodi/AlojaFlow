<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Empresas\RelationManagers;

use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Las reservas de una empresa: solo para ver. Se cambian en su ficha.
 */
class ReservasRelationManager extends RelationManager
{
    protected static string $relationship = 'reservas';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('caja.empresas.reservas');
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => Limites::reservasVisibles($query->with('titular'), Acceso::usuarioActual()))
            ->defaultSort('llegada', 'desc')
            ->columns([
                TextColumn::make('numero')->label(__('informes.columnas.reserva'))
                    ->url(fn (Reserva $record): ?string => ReservaResource::canView($record) ? ReservaResource::getUrl('view', ['record' => $record]) : null),
                TextColumn::make('titular_id')->label(__('ui.comun.huesped'))->placeholder('—')
                    ->formatStateUsing(fn (Reserva $record): string => (string) $record->titular?->nombreCompleto()),
                TextColumn::make('llegada')->label(__('ui.comun.llegada'))->sortable()->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->llegada)),
                TextColumn::make('salida')->label(__('ui.comun.salida'))->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->salida)),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()->color('gray')->formatStateUsing(fn (Reserva $record): string => $record->estado->etiqueta()),
                TextColumn::make('total')->label(__('ui.comun.total'))->alignEnd()->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->total, $record->moneda)),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->pendiente, $record->moneda)),
            ])
            ->emptyStateHeading(__('caja.empresas.sin_reservas'));
    }
}
