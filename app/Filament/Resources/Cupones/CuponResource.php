<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Cupones;

use App\Enums\TipoCupon;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Cupones\Pages\CreateCupon;
use App\Filament\Resources\Cupones\Pages\EditCupon;
use App\Filament\Resources\Cupones\Pages\ListCupones;
use App\Models\Cupon;
use App\Reservas\Cupones;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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
 * Códigos de descuento, para reservas del personal y del motor de reservas.
 */
class CuponResource extends RecursoDePropiedad
{
    protected static ?string $model = Cupon::class;

    protected static ?string $slug = 'cupones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('ui.cupones.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.cupones.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $esPorcentaje = fn (Get $get): bool => ($get('tipo') instanceof TipoCupon ? $get('tipo') : TipoCupon::tryFrom((string) $get('tipo'))) === TipoCupon::Porcentaje;

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('codigo')->label(__('ui.cupones.codigo'))->required()->maxLength(40)->alphaDash()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('descripcion')->label(__('ui.cupones.descripcion'))->maxLength(150),
                Select::make('tipo')->label(__('ui.cupones.tipo'))->options(TipoCupon::opciones())->required()->native(false)->live()->default(TipoCupon::Porcentaje->value),
                TextInput::make('porcentaje')->label(__('ui.cupones.porcentaje'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')
                    ->required($esPorcentaje)->visible($esPorcentaje),
                CampoDinero::make('importe')->label(__('ui.cupones.importe'))
                    ->required(fn (Get $get): bool => ! $esPorcentaje($get))->visible(fn (Get $get): bool => ! $esPorcentaje($get)),
                DatePicker::make('valido_desde')->label(__('ui.cupones.desde'))->helperText(__('ui.cupones.fechas_ayuda')),
                DatePicker::make('valido_hasta')->label(__('ui.cupones.hasta'))->afterOrEqual('valido_desde'),
                TextInput::make('usos_maximos')->label(__('ui.cupones.usos_maximos'))->helperText(__('ui.cupones.usos_ayuda'))->numeric()->minValue(1),
                TextInput::make('estancia_minima')->label(__('ui.cupones.estancia_minima'))->numeric()->minValue(1)->default(1)->required(),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('codigo')
            ->columns([
                TextColumn::make('codigo')->label(__('ui.cupones.codigo'))->searchable()->copyable(),
                self::columnaPropiedad(),
                TextColumn::make('valor')->label(__('ui.cupones.valor'))
                    ->state(fn (Cupon $record): string => $record->tipo === TipoCupon::Porcentaje ? (float) $record->porcentaje.' %' : Formato::dinero((int) $record->importe, $record->propiedad->moneda)),
                TextColumn::make('valido_hasta')->label(__('ui.cupones.hasta'))->placeholder('—')
                    ->formatStateUsing(fn (Cupon $record): string => Formato::fecha($record->valido_hasta)),
                TextColumn::make('usos')->label(__('ui.cupones.usos'))->alignEnd()
                    ->state(fn (Cupon $record): string => Cupones::usos($record).($record->usos_maximos ? ' / '.$record->usos_maximos : '')),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('ui.cupones.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Cupon $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCupones::route('/'),
            'create' => CreateCupon::route('/create'),
            'edit' => EditCupon::route('/{record}/edit'),
        ];
    }
}
