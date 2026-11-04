<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Impuestos;

use App\Enums\BaseCargo;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Impuestos\Pages\CreateImpuesto;
use App\Filament\Resources\Impuestos\Pages\EditImpuesto;
use App\Filament\Resources\Impuestos\Pages\ListImpuestos;
use App\Models\Impuesto;
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

/**
 * Impuestos de una propiedad independiente, incluidos en el precio o que se suman.
 */
class ImpuestoResource extends RecursoDePropiedad
{
    protected static ?string $model = Impuesto::class;

    protected static ?string $slug = 'impuestos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('ui.impuestos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.impuestos.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $esPorcentaje = fn (Get $get): bool => self::base($get) === BaseCargo::PorcentajeAlojamiento;

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.impuestos.nombre'))->helperText(__('ui.impuestos.nombre_ayuda'))->required()->maxLength(100),
                Select::make('base')->label(__('ui.impuestos.base'))->required()->native(false)->live()
                    ->options([
                        BaseCargo::PorcentajeAlojamiento->value => BaseCargo::PorcentajeAlojamiento->etiqueta(),
                        BaseCargo::PorNoche->value => BaseCargo::PorNoche->etiqueta(),
                        BaseCargo::PorPersonaNoche->value => BaseCargo::PorPersonaNoche->etiqueta(),
                        BaseCargo::PorEstancia->value => BaseCargo::PorEstancia->etiqueta(),
                    ])
                    ->default(BaseCargo::PorcentajeAlojamiento->value),
                TextInput::make('tasa')->label(__('ui.impuestos.tasa'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')
                    ->required($esPorcentaje)->visible($esPorcentaje),
                CampoDinero::make('importe')->label(__('ui.impuestos.importe'))->required(fn (Get $get): bool => ! $esPorcentaje($get))->visible(fn (Get $get): bool => ! $esPorcentaje($get)),
                Toggle::make('incluido_en_precio')->label(__('ui.impuestos.incluido'))->helperText(__('ui.impuestos.incluido_ayuda'))->default(true),
                TextInput::make('codigo')->label(__('ui.impuestos.codigo'))->helperText(__('ui.impuestos.codigo_ayuda'))->maxLength(30),
                TextInput::make('orden')->label(__('ui.unidades.orden'))->numeric()->default(0),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('orden')
            ->columns([
                TextColumn::make('nombre')->label(__('ui.impuestos.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('base')->label(__('ui.impuestos.base'))->formatStateUsing(fn (Impuesto $record): string => $record->base->etiqueta()),
                TextColumn::make('tasa')->label(__('ui.impuestos.tasa'))->suffix(' %')->placeholder('—'),
                IconColumn::make('incluido_en_precio')->label(__('ui.impuestos.incluido'))->boolean(),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.impuestos.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Impuesto $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListImpuestos::route('/'),
            'create' => CreateImpuesto::route('/create'),
            'edit' => EditImpuesto::route('/{record}/edit'),
        ];
    }

    private static function base(Get $get): ?BaseCargo
    {
        $base = $get('base');

        return $base instanceof BaseCargo ? $base : BaseCargo::tryFrom((string) $base);
    }
}
