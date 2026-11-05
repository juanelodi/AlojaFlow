<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Opiniones;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Opiniones\Pages\ListOpiniones;
use App\Models\Opinion;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo que contestaron los huéspedes (D-087). Nadie lo edita: es lo que dijeron.
 */
class OpinionResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Opinion::class;

    protected static ?string $slug = 'opiniones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedStar;

    protected static ?int $navigationSort = 5;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Opiniones;
    }

    protected static function permiso(): string
    {
        return Acceso::MENSAJES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getModelLabel(): string
    {
        return __('operacion.opiniones.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('operacion.opiniones.modelos');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNotNull('respondida_en');
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

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'reserva']))
            ->defaultSort('respondida_en', 'desc')
            ->columns([
                TextColumn::make('respondida_en')->label(__('operacion.opiniones.fecha'))->sortable()
                    ->formatStateUsing(fn (Opinion $record): string => Formato::momento($record->respondida_en, $record->propiedad->zona_horaria)),
                self::columnaPropiedad(),
                TextColumn::make('reserva.numero')->label(__('informes.columnas.reserva')),
                TextColumn::make('calificacion')->label(__('operacion.opiniones.calificacion'))->sortable()->badge()
                    ->color(fn (Opinion $record): string => match (true) {
                        (int) $record->calificacion >= 4 => 'success',
                        (int) $record->calificacion === 3 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('comentario')->label(__('operacion.opiniones.comentario'))->placeholder('—')->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('calificacion')->label(__('operacion.opiniones.calificacion'))->options([5 => '5', 4 => '4', 3 => '3', 2 => '2', 1 => '1']),
            ])
            ->emptyStateHeading(__('operacion.opiniones.vacio'))
            ->emptyStateDescription(__('operacion.opiniones.vacio_ayuda'));
    }

    public static function getPages(): array
    {
        return ['index' => ListOpiniones::route('/')];
    }
}
