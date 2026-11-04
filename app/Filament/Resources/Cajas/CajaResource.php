<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Cajas;

use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Cajas\Pages\CreateCaja;
use App\Filament\Resources\Cajas\Pages\EditCaja;
use App\Filament\Resources\Cajas\Pages\ListCajas;
use App\Models\Caja;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Cajas o cuentas a las que entra y de las que sale el dinero.
 */
class CajaResource extends RecursoDePropiedad
{
    protected static ?string $model = Caja::class;

    protected static ?string $slug = 'cajas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxStack;

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('ui.cajas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.cajas.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.cajas.nombre'))->helperText(__('ui.cajas.nombre_ayuda'))->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', (int) $get('propiedad_id'))),
                Toggle::make('activa')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.cajas.nombre'))->searchable(),
                self::columnaPropiedad(),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.cajas.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Caja $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCajas::route('/'),
            'create' => CreateCaja::route('/create'),
            'edit' => EditCaja::route('/{record}/edit'),
        ];
    }
}
