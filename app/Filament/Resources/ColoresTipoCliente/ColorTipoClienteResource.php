<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\ColoresTipoCliente;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\ColoresTipoCliente\Pages\CreateColorTipoCliente;
use App\Filament\Resources\ColoresTipoCliente\Pages\EditColorTipoCliente;
use App\Filament\Resources\ColoresTipoCliente\Pages\ListColoresTipoCliente;
use App\Models\ColorTipoCliente;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pinta en el calendario las reservas según un campo de la ficha: empresa, agencia, VIP…
 */
class ColorTipoClienteResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = ColorTipoCliente::class;

    protected static ?string $slug = 'colores-de-clientes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 10;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getModelLabel(): string
    {
        return __('plano.colores.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('plano.colores.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('plano.colores.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('campo')->label(__('plano.colores.campo'))->helperText(__('plano.colores.campo_ayuda'))->required()->maxLength(100),
                TextInput::make('valor')->label(__('plano.colores.valor'))->required()->maxLength(255),
                ColorPicker::make('color')->label(__('plano.colores.color'))->required()->regex('/^#[0-9a-fA-F]{6}$/'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('campo')
            ->columns([
                TextColumn::make('campo')->label(__('plano.colores.campo'))->searchable(),
                TextColumn::make('valor')->label(__('plano.colores.valor'))->searchable(),
                self::columnaPropiedad(),
                ColorColumn::make('color')->label(__('plano.colores.color')),
            ])
            ->emptyStateHeading(__('plano.colores.vacio'))
            ->recordActions([EditAction::make()->visible(fn (ColorTipoCliente $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListColoresTipoCliente::route('/'),
            'create' => CreateColorTipoCliente::route('/create'),
            'edit' => EditColorTipoCliente::route('/{record}/edit'),
        ];
    }
}
