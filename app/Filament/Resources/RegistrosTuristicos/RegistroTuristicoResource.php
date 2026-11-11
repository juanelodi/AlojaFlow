<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\RegistrosTuristicos;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\RegistrosTuristicos\Pages\CreateRegistroTuristico;
use App\Filament\Resources\RegistrosTuristicos\Pages\EditRegistroTuristico;
use App\Filament\Resources\RegistrosTuristicos\Pages\ListRegistrosTuristicos;
use App\Models\RegistroTuristico;
use App\Models\Unidad;
use App\Seguridad\Acceso;
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

/**
 * Números de registro o licencia, con su vigencia (D-091). Vale también en una subordinada: el registro no depende de quién lleve las reservas.
 */
class RegistroTuristicoResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = RegistroTuristico::class;

    protected static ?string $slug = 'registros-turisticos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 7;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getModelLabel(): string
    {
        return __('herramientas.registro.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('herramientas.registro.modelos');
    }

    protected static function referencias(): array
    {
        return ['unidad_id' => Unidad::class];
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('herramientas.registro.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('unidad_id')->label(__('ui.comun.unidad'))->native(false)->placeholder(__('herramientas.registro.toda'))
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', self::propiedadDe($get))->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('tipo')->label(__('herramientas.registro.tipo'))->helperText(__('herramientas.registro.tipo_ayuda'))->required()->maxLength(100),
                TextInput::make('numero')->label(__('herramientas.registro.numero'))->required()->maxLength(100),
                DatePicker::make('vigente_hasta')->label(__('herramientas.registro.vigente_hasta'))->helperText(__('herramientas.registro.vigente_hasta_ayuda')),
                Toggle::make('mostrar')->label(__('herramientas.registro.mostrar'))->helperText(__('herramientas.registro.mostrar_ayuda'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'unidad']))
            ->defaultSort('vigente_hasta')
            ->columns([
                TextColumn::make('tipo')->label(__('herramientas.registro.tipo'))->searchable(),
                TextColumn::make('numero')->label(__('herramientas.registro.numero'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('unidad.nombre')->label(__('ui.comun.unidad'))->placeholder(__('herramientas.registro.toda')),
                TextColumn::make('vigente_hasta')->label(__('herramientas.registro.vigente_hasta'))->placeholder('—')->sortable()
                    ->formatStateUsing(fn (RegistroTuristico $record): string => Formato::fecha($record->vigente_hasta)),
                IconColumn::make('mostrar')->label(__('herramientas.registro.mostrar'))->boolean(),
            ])
            ->emptyStateHeading(__('herramientas.registro.vacio'))
            ->recordActions([EditAction::make()->visible(fn (RegistroTuristico $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrosTuristicos::route('/'),
            'create' => CreateRegistroTuristico::route('/create'),
            'edit' => EditRegistroTuristico::route('/{record}/edit'),
        ];
    }
}
