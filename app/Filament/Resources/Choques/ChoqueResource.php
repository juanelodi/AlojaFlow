<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Choques;

use App\Enums\TipoChoque;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Choques\Pages\ListChoques;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Choque;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Ocupaciones que coinciden: un portal y una reserva, o dos reservas de una principal.
 */
class ChoqueResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Choque::class;

    protected static ?string $slug = 'choques';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 6;

    protected static function permiso(): string
    {
        return Acceso::OPERACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getModelLabel(): string
    {
        return __('ical.choques.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ical.choques.modelos');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $abiertos = Choque::query()->whereIn('propiedad_id', self::propiedadesGestionables())->whereNull('resuelto_en')->count();

        return $abiertos > 0 ? (string) $abiertos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'unidad', 'reserva.titular', 'reserva2.titular', 'bloqueoExterno.fuente']))
            ->defaultSort('desde')
            ->columns([
                TextColumn::make('desde')->label(__('ical.choques.desde'))->date()->sortable(),
                TextColumn::make('hasta')->label(__('ical.choques.hasta'))->date(),
                TextColumn::make('unidad.nombre')->label(__('ical.choques.unidad')),
                self::columnaPropiedad(),
                TextColumn::make('reserva.numero')->label(__('ical.choques.reserva'))
                    ->url(fn (Choque $record): string => ReservaResource::getUrl('view', ['record' => $record->reserva_id])),
                TextColumn::make('tipo')->label(__('ical.choques.con'))->state(fn (Choque $record): string => $record->tipo === TipoChoque::BloqueoReserva
                    ? (string) $record->bloqueoExterno?->fuente->nombre
                    : __('ical.choques.reserva_n', ['numero' => $record->reserva2->numero ?? ''])),
                TextColumn::make('nota')->label(__('ical.choques.nota'))->limit(40)->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('abiertos')->label(__('ical.choques.abiertos'))->default(true)
                    ->queries(
                        true: fn (Builder $q): Builder => $q->whereNull('resuelto_en'),
                        false: fn (Builder $q): Builder => $q->whereNotNull('resuelto_en'),
                    ),
            ])
            ->emptyStateHeading(__('ical.choques.vacio'))
            ->recordActions([
                Action::make('resolver')
                    ->label(__('ical.choques.resolver'))
                    ->icon(Heroicon::OutlinedCheck)
                    ->visible(fn (Choque $record): bool => $record->resuelto_en === null)
                    ->schema([Textarea::make('nota')->label(__('ical.choques.nota'))->rows(2)->maxLength(1000)])
                    ->action(fn (Choque $record, array $data) => $record->update(['resuelto_en' => now('UTC'), 'resuelto_por' => auth()->id(), 'nota' => $data['nota'] ?? null])),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListChoques::route('/')];
    }
}
