<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Tables;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Support\Formato;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['unidad', 'titular', 'propiedad']))
            ->columns([
                TextColumn::make('numero')->label(__('ui.reservas.numero'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('numero', 'like', $search.'%')),
                TextColumn::make('titular.apellido1')->label(__('ui.comun.huesped'))
                    ->formatStateUsing(fn (mixed $state, Reserva $record): string => $record->titular?->nombreCompleto() ?? '—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('titular', fn (Builder $h): Builder => $h
                        ->where('apellido1', 'like', '%'.$search.'%')->orWhere('nombre', 'like', '%'.$search.'%')->orWhere('apellido2', 'like', '%'.$search.'%'))),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad')),
                TextColumn::make('llegada')->label(__('ui.comun.llegada'))->sortable()
                    ->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->llegada)),
                TextColumn::make('salida')->label(__('ui.comun.salida'))->sortable()
                    ->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->salida)),
                TextColumn::make('noches')->label(__('ui.comun.noches'))->alignEnd()->visibleFrom('md'),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->formatStateUsing(fn (Reserva $record): string => $record->eliminada_en_principal_en ? __('ui.reservas.eliminada') : ($record->tipo === TipoReserva::Bloqueo ? TipoReserva::Bloqueo->etiqueta() : $record->estado->etiqueta()))
                    ->color(fn (Reserva $record): string => match (true) {
                        $record->eliminada_en_principal_en !== null => 'danger',
                        $record->tipo === TipoReserva::Bloqueo => 'gray',
                        $record->estado === EstadoReserva::Cancelada => 'danger',
                        $record->estado === EstadoReserva::SinConfirmar => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('total')->label(__('ui.comun.total'))->alignEnd()->sortable()->visibleFrom('md')
                    ->formatStateUsing(fn (Reserva $record): string => Formato::dinero($record->total, $record->moneda)),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()->sortable()
                    ->formatStateUsing(fn (Reserva $record): string => $record->pendiente > 0 ? Formato::dinero($record->pendiente, $record->moneda) : '—'),
                TextColumn::make('origen_texto')->label(__('ui.comun.origen'))->toggleable()->visibleFrom('md'),
            ])
            ->defaultSort('llegada', 'desc')
            ->filters([
                Filter::make('vigentes')->label(__('ui.reservas.solo_vigentes'))->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull('eliminada_en_principal_en')),
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))
                    ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesPermitidas())->orderBy('nombre')->pluck('nombre', 'id')->all()),
                SelectFilter::make('estado')->label(__('ui.comun.estado'))->options(EstadoReserva::opciones()),
                SelectFilter::make('tipo')->label(__('ui.reservas.tipo'))->options(TipoReserva::opciones()),
                Filter::make('llegada')->label(__('ui.reservas.filtro_llegada'))
                    ->schema([
                        DatePicker::make('desde')->label(__('ui.indicadores.desde')),
                        DatePicker::make('hasta')->label(__('ui.indicadores.hasta')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->where('llegada', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->where('llegada', '<=', $hasta))),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->visible(fn (Reserva $record): bool => ReservaResource::canEdit($record)),
            ])
            ->recordUrl(fn (Reserva $record): string => ReservaResource::getUrl('view', ['record' => $record]));
    }
}
