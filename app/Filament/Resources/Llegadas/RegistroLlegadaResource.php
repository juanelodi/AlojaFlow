<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Llegadas;

use App\Enums\EstadoRegistroLlegada;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Llegadas\Pages\ListLlegadas;
use App\Filament\Resources\Llegadas\Pages\ViewLlegada;
use App\Models\RegistroLlegada;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que mandaron los huéspedes antes de llegar.
 */
class RegistroLlegadaResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = RegistroLlegada::class;

    protected static ?string $slug = 'llegadas-en-linea';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 7;

    protected static function permiso(): string
    {
        return Acceso::HUESPEDES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getModelLabel(): string
    {
        return __('llegada.panel.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('llegada.panel.modelos');
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

    public static function canView(Model $record): bool
    {
        return in_array((int) $record->getAttribute('propiedad_id'), self::propiedadesGestionables(), true);
    }

    public static function getNavigationBadge(): ?string
    {
        $porRevisar = RegistroLlegada::query()->whereIn('propiedad_id', self::propiedadesGestionables())->where('estado', EstadoRegistroLlegada::Recibido->value)->count();

        return $porRevisar > 0 ? (string) $porRevisar : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'reserva.titular'])->where('estado', '!=', EstadoRegistroLlegada::Pendiente->value))
            ->defaultSort('recibido_en', 'desc')
            ->columns([
                TextColumn::make('reserva.numero')->label(__('llegada.panel.reserva')),
                TextColumn::make('huesped')->label(__('llegada.panel.huesped'))->state(fn (RegistroLlegada $record): string => (string) $record->reserva->titular?->nombreCompleto()),
                TextColumn::make('reserva.llegada')->label(__('llegada.panel.llegada'))->date(),
                self::columnaPropiedad(),
                TextColumn::make('recibido_en')->label(__('llegada.panel.recibido'))->state(fn (RegistroLlegada $record): string => Formato::momento($record->recibido_en, $record->propiedad->zona_horaria))->toggleable(),
                TextColumn::make('estado')->label(__('llegada.panel.estado'))->badge()->formatStateUsing(fn (EstadoRegistroLlegada $state): string => $state->etiqueta())
                    ->color(fn (EstadoRegistroLlegada $state): string => $state === EstadoRegistroLlegada::Recibido ? 'warning' : 'success'),
            ])
            ->filters([SelectFilter::make('estado')->label(__('llegada.panel.estado'))->options([EstadoRegistroLlegada::Recibido->value => EstadoRegistroLlegada::Recibido->etiqueta(), EstadoRegistroLlegada::Revisado->value => EstadoRegistroLlegada::Revisado->etiqueta()])])
            ->emptyStateHeading(__('llegada.panel.vacio'))
            ->recordActions([ViewAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLlegadas::route('/'),
            'view' => ViewLlegada::route('/{record}'),
        ];
    }
}
