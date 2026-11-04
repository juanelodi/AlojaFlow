<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Plantillas;

use App\Comunicacion\VariablesMensaje;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Plantillas\Pages\CreatePlantilla;
use App\Filament\Resources\Plantillas\Pages\EditPlantilla;
use App\Filament\Resources\Plantillas\Pages\ListPlantillas;
use App\Models\Automatizacion;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Los textos de los mensajes, por idioma.
 */
class PlantillaResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Plantilla::class;

    protected static ?string $slug = 'plantillas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?int $navigationSort = 2;

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getModelLabel(): string
    {
        return __('mensajes.plantillas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mensajes.plantillas.modelos');
    }

    public static function canDelete(Model $record): bool
    {
        return parent::canDelete($record) && ! Automatizacion::query()->where('plantilla_id', $record->getKey())->exists();
    }

    public static function form(Schema $schema): Schema
    {
        $pestanas = [];
        foreach (Formato::idiomas() as $codigo => $nombre) {
            $esDeLaPropiedad = fn (Get $get): bool => Propiedad::query()->whereKey(self::propiedadDe($get))->value('idioma') === $codigo;
            $pestanas[] = Tab::make($nombre)->schema([
                TextInput::make('asunto.'.$codigo)->label(__('mensajes.plantillas.asunto'))->maxLength(200)->required($esDeLaPropiedad),
                Textarea::make('cuerpo.'.$codigo)->label(__('mensajes.plantillas.cuerpo'))->rows(10)->maxLength(10000)->required($esDeLaPropiedad),
            ]);
        }

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('mensajes.plantillas.nombre'))->required()->maxLength(100),
                TextInput::make('codigo')->label(__('mensajes.plantillas.codigo'))->helperText(__('mensajes.plantillas.codigo_ayuda'))->required()->maxLength(50)->alphaDash()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get))),
                Toggle::make('activa')->label(__('mensajes.plantillas.activa'))->default(true),
            ]),
            Section::make()->columnSpanFull()->description(__('mensajes.plantillas.variables').' '.VariablesMensaje::ayuda())->schema([
                Tabs::make()->tabs($pestanas),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('propiedad'))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('mensajes.plantillas.nombre'))->searchable(),
                self::columnaPropiedad(),
                IconColumn::make('activa')->label(__('mensajes.plantillas.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('mensajes.plantillas.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Plantilla $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlantillas::route('/'),
            'create' => CreatePlantilla::route('/create'),
            'edit' => EditPlantilla::route('/{record}/edit'),
        ];
    }
}
