<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\CamposPropios;

use App\Enums\EntidadCampo;
use App\Enums\TipoCampoPropio;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\CamposPropios\Pages\CreateCampoPropio;
use App\Filament\Resources\CamposPropios\Pages\EditCampoPropio;
use App\Filament\Resources\CamposPropios\Pages\ListCamposPropios;
use App\Models\CampoPropio;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Los datos que cada alojamiento quiere guardar y el sistema no pide (D-122).
 */
class CampoPropioResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = CampoPropio::class;

    protected static ?string $slug = 'campos-propios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 11;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Documentos;
    }

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('documentos_propios.campos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('documentos_propios.campos.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('documentos_propios.campos.nombre'))->helperText(__('documentos_propios.campos.nombre_ayuda'))->required()->maxLength(100),
                Select::make('entidad')->label(__('documentos_propios.campos.entidad'))->options(EntidadCampo::opciones())->required()->native(false)
                    ->default(EntidadCampo::Reserva->value)->disabledOn('edit'),
                Select::make('tipo')->label(__('documentos_propios.campos.tipo'))->options(TipoCampoPropio::opciones())->required()->native(false)
                    ->default(TipoCampoPropio::Texto->value)->disabledOn('edit'),
                TextInput::make('orden')->label(__('ui.unidades.orden'))->numeric()->integer()->minValue(0)->maxValue(60000)->default(0),
                Toggle::make('activo')->label(__('documentos_propios.campos.activo'))->helperText(__('documentos_propios.campos.activo_ayuda'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label(__('documentos_propios.campos.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('entidad')->label(__('documentos_propios.campos.entidad'))->formatStateUsing(fn (CampoPropio $record): string => $record->entidad->etiqueta()),
                TextColumn::make('tipo')->label(__('documentos_propios.campos.tipo'))->formatStateUsing(fn (CampoPropio $record): string => $record->tipo->etiqueta()),
                TextColumn::make('clave')->label(__('documentos_propios.campos.variable'))->copyable()->formatStateUsing(fn (CampoPropio $record): string => $record->variable()),
                IconColumn::make('activo')->label(__('documentos_propios.campos.activo'))->boolean(),
            ])
            ->emptyStateHeading(__('documentos_propios.campos.vacio'))
            ->emptyStateDescription(__('documentos_propios.campos.vacio_ayuda'))
            ->recordActions([EditAction::make()->visible(fn (CampoPropio $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCamposPropios::route('/'),
            'create' => CreateCampoPropio::route('/create'),
            'edit' => EditCampoPropio::route('/{record}/edit'),
        ];
    }
}
