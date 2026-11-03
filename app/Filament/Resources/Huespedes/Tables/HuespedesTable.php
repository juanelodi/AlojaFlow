<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Tables;

use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HuespedesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contactos', 'propiedad'])->withCount('reservasComoTitular'))
            ->columns([
                TextColumn::make('apellido1')->label(__('ui.huespedes.nombre'))->sortable()
                    ->formatStateUsing(fn (Huesped $record): string => $record->nombreCompleto())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(fn (Builder $q): Builder => $q
                        ->where('apellido1', 'like', '%'.$search.'%')->orWhere('apellido2', 'like', '%'.$search.'%')->orWhere('nombre', 'like', '%'.$search.'%')
                        ->orWhereHas('contactos', fn (Builder $c): Builder => $c->where('valor_normalizado', 'like', '%'.mb_strtolower($search).'%')))),
                TextColumn::make('contactos.valor')->label(__('ui.huespedes.contactos'))
                    ->state(fn (Huesped $record): array => $record->contactos->pluck('valor')->take(2)->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('nacionalidad_texto')->label(__('ui.huespedes.nacionalidad'))->placeholder('—')->toggleable(),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1),
                TextColumn::make('reservas_como_titular_count')->label(__('ui.huespedes.reservas'))->alignEnd()->sortable(),
            ])
            ->defaultSort('apellido1')
            ->filters([
                Filter::make('vigentes')->label(__('ui.reservas.solo_vigentes'))->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('eliminado_en_principal_en')),
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))
                    ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesPermitidas())->orderBy('nombre')->pluck('nombre', 'id')->all()),
            ])
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Huesped $record): string => HuespedResource::getUrl('view', ['record' => $record]));
    }
}
