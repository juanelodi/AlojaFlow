<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\MantenimientosRecurrentes;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\MantenimientosRecurrentes\Pages\CreateMantenimientoRecurrente;
use App\Filament\Resources\MantenimientosRecurrentes\Pages\EditMantenimientoRecurrente;
use App\Filament\Resources\MantenimientosRecurrentes\Pages\ListMantenimientosRecurrentes;
use App\Models\MantenimientoRecurrente;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
 * Trabajos que se repiten: al acercarse la fecha generan una tarea.
 */
class MantenimientoRecurrenteResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = MantenimientoRecurrente::class;

    protected static ?string $slug = 'mantenimiento-recurrente';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 31;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Limpieza;
    }

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    /**
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected static function referencias(): array
    {
        return ['unidad_id' => Unidad::class];
    }

    public static function getModelLabel(): string
    {
        return __('equipo.recurrentes.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipo.recurrentes.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->description(__('equipo.recurrentes.ayuda'))->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('unidad_id')->label(__('ui.comun.unidad'))->helperText(__('equipo.recurrentes.unidad_ayuda'))->native(false)
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('titulo')->label(__('equipo.recurrentes.titulo'))->required()->maxLength(150)->columnSpanFull(),
                Textarea::make('descripcion')->label(__('equipo.recurrentes.descripcion'))->rows(2)->maxLength(2000)->columnSpanFull(),
                TextInput::make('cada_dias')->label(__('equipo.recurrentes.cada_dias'))->numeric()->required()->minValue(1)->maxValue(3650)->default(30),
                DatePicker::make('proxima_fecha')->label(__('equipo.recurrentes.proxima'))->required()->native(false)->default(fn (): string => now()->format('Y-m-d')),
                Toggle::make('activo')->label(__('ui.tarifas.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'unidad']))
            ->defaultSort('proxima_fecha')
            ->columns([
                TextColumn::make('titulo')->label(__('equipo.recurrentes.titulo'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad'))->placeholder('—'),
                TextColumn::make('cada_dias')->label(__('equipo.recurrentes.cada_dias')),
                TextColumn::make('proxima_fecha')->label(__('equipo.recurrentes.proxima'))
                    ->formatStateUsing(fn (MantenimientoRecurrente $record): string => Formato::fecha($record->proxima_fecha)),
                IconColumn::make('activo')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('equipo.recurrentes.vacio'))
            ->recordActions([EditAction::make()->visible(fn (MantenimientoRecurrente $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMantenimientosRecurrentes::route('/'),
            'create' => CreateMantenimientoRecurrente::route('/create'),
            'edit' => EditMantenimientoRecurrente::route('/{record}/edit'),
        ];
    }
}
