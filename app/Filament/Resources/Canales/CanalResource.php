<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Canales;

use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Canales\Pages\CreateCanal;
use App\Filament\Resources\Canales\Pages\EditCanal;
use App\Filament\Resources\Canales\Pages\ListCanales;
use App\Models\Canal;
use App\Models\Politica;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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
 * De dónde vienen las reservas, con su comisión: se sugiere al elegir el origen de una reserva.
 */
class CanalResource extends RecursoDePropiedad
{
    protected static ?string $model = Canal::class;

    protected static ?string $slug = 'canales';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('ui.canales.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.canales.modelos');
    }

    protected static function referencias(): array
    {
        return ['politica_id' => Politica::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.canales.nombre'))->helperText(__('ui.canales.nombre_ayuda'))->required()->maxLength(100)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('comision_porcentaje')->label(__('ui.canales.comision'))->helperText(__('ui.canales.comision_ayuda'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%'),
                Select::make('politica_id')->label(__('dinero.politicas.modelo'))->native(false)
                    ->options(fn (Get $get): array => Politica::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Toggle::make('cobra_impuestos')->label(__('caja.impuestos.canal_cobra'))->helperText(__('caja.impuestos.canal_cobra_ayuda')),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.canales.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('comision_porcentaje')->label(__('ui.canales.comision'))->suffix(' %')->placeholder('—')->alignEnd(),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.canales.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Canal $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCanales::route('/'),
            'create' => CreateCanal::route('/create'),
            'edit' => EditCanal::route('/{record}/edit'),
        ];
    }
}
