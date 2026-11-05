<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Restricciones;

use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Restricciones\Pages\CreateRestriccion;
use App\Filament\Resources\Restricciones\Pages\EditRestriccion;
use App\Filament\Resources\Restricciones\Pages\ListRestricciones;
use App\Models\Restriccion;
use App\Models\Unidad;
use App\Support\Formato;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
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

/**
 * Restricciones de llegada y salida (D-084). El motor las cumple; al personal solo se le avisa.
 */
class RestriccionResource extends RecursoDePropiedad
{
    protected static ?string $model = Restriccion::class;

    protected static ?string $slug = 'restricciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('operacion.restricciones.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('operacion.restricciones.modelos');
    }

    protected static function referencias(): array
    {
        return ['unidad_id' => Unidad::class];
    }

    /**
     * @return array<int, string> del lunes (1) al domingo (7)
     */
    private static function dias(): array
    {
        $dias = [];
        for ($i = 1; $i <= 7; $i++) {
            $dias[$i] = (string) CarbonImmutable::create(2024, 1, $i)?->locale(app()->getLocale())->isoFormat('dddd');
        }

        return $dias;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('operacion.restricciones.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('unidad_id')->label(__('ui.comun.unidad'))->native(false)->placeholder(__('operacion.restricciones.todas'))
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('nombre')->label(__('operacion.restricciones.nombre'))->helperText(__('operacion.restricciones.nombre_ayuda'))->maxLength(100),
                DatePicker::make('desde')->label(__('ui.indicadores.desde'))->required(),
                DatePicker::make('hasta')->label(__('ui.indicadores.hasta'))->required()->afterOrEqual('desde'),
                Toggle::make('cerrado_llegada')->label(__('operacion.restricciones.cerrado_llegada')),
                Toggle::make('cerrado_salida')->label(__('operacion.restricciones.cerrado_salida')),
                CheckboxList::make('dias_llegada')->label(__('operacion.restricciones.dias_llegada'))->helperText(__('operacion.restricciones.dias_llegada_ayuda'))->columnSpanFull()->columns(['default' => 2, 'md' => 4])
                    ->options(fn (): array => self::dias())
                    ->formatStateUsing(fn (mixed $state): array => array_values(array_filter(array_keys(self::dias()), fn (int $dia): bool => ((int) $state & (1 << ($dia - 1))) !== 0)))
                    ->dehydrateStateUsing(fn (mixed $state): ?int => array_reduce((array) $state, fn (int $mascara, mixed $dia): int => $mascara | (1 << ((int) $dia - 1)), 0) ?: null),
                TextInput::make('estancia_maxima')->label(__('operacion.restricciones.estancia_maxima'))->numeric()->minValue(1)->maxValue(3650),
                TextInput::make('anticipacion_maxima')->label(__('operacion.restricciones.anticipacion_maxima'))->helperText(__('operacion.restricciones.anticipacion_maxima_ayuda'))->numeric()->minValue(0)->maxValue(3650),
                TextInput::make('noches_preparacion')->label(__('operacion.restricciones.noches_preparacion'))->helperText(__('operacion.restricciones.noches_preparacion_ayuda'))->numeric()->minValue(1)->maxValue(30),
                Toggle::make('activa')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'unidad']))
            ->defaultSort('desde', 'desc')
            ->columns([
                TextColumn::make('nombre')->label(__('operacion.restricciones.nombre'))->placeholder('—')->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad'))->placeholder(__('operacion.restricciones.todas')),
                TextColumn::make('desde')->label(__('ui.indicadores.desde'))->sortable()->formatStateUsing(fn (Restriccion $record): string => Formato::fecha($record->desde)),
                TextColumn::make('hasta')->label(__('ui.indicadores.hasta'))->formatStateUsing(fn (Restriccion $record): string => Formato::fecha($record->hasta)),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('operacion.restricciones.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Restriccion $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRestricciones::route('/'),
            'create' => CreateRestriccion::route('/create'),
            'edit' => EditRestriccion::route('/{record}/edit'),
        ];
    }
}
