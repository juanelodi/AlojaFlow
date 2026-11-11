<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\ListasComprobacion;

use App\Enums\TipoTarea;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\ListasComprobacion\Pages\CreateListaComprobacion;
use App\Filament\Resources\ListasComprobacion\Pages\EditListaComprobacion;
use App\Filament\Resources\ListasComprobacion\Pages\ListListasComprobacion;
use App\Models\ListaComprobacion;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
 * Lo que hay que revisar en cada tipo de tarea: una lista por tipo y propiedad.
 */
class ListaComprobacionResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = ListaComprobacion::class;

    protected static ?string $slug = 'listas-de-comprobacion';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 30;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Limpieza;
    }

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getModelLabel(): string
    {
        return __('equipo.listas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipo.listas.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->description(__('equipo.listas.ayuda'))->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Select::make('tipo_tarea')->label(__('equipo.listas.tipo'))->options(TipoTarea::opciones())->required()->native(false)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                TextInput::make('nombre')->label(__('equipo.listas.nombre'))->required()->maxLength(100),
                Toggle::make('activa')->label(__('ui.tarifas.activa'))->default(true),
                Repeater::make('puntos')->label(__('equipo.listas.puntos'))->columnSpanFull()->required()->minItems(1)->maxItems(50)
                    ->simple(TextInput::make('punto')->label(__('equipo.listas.punto'))->required()->maxLength(150))
                    ->addActionLabel(__('equipo.listas.anadir')),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('equipo.listas.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('tipo_tarea')->label(__('equipo.listas.tipo'))->formatStateUsing(fn (ListaComprobacion $record): string => $record->tipo_tarea->etiqueta()),
                TextColumn::make('cuantos')->label(__('equipo.listas.puntos'))->state(fn (ListaComprobacion $record): int => count($record->puntos)),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('equipo.listas.vacio'))
            ->recordActions([EditAction::make()->visible(fn (ListaComprobacion $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListListasComprobacion::route('/'),
            'create' => CreateListaComprobacion::route('/create'),
            'edit' => EditListaComprobacion::route('/{record}/edit'),
        ];
    }
}
