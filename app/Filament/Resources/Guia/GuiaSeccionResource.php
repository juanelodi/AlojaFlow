<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Guia;

use App\Enums\MomentoGuia;
use App\Enums\TipoSeccionGuia;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Guia\Pages\CreateGuiaSeccion;
use App\Filament\Resources\Guia\Pages\EditGuiaSeccion;
use App\Filament\Resources\Guia\Pages\ListGuiaSecciones;
use App\Models\GuiaSeccion;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Publico\Textos;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
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

/**
 * La guía digital, por partes: la red, las reglas, cómo llegar… (D-106).
 */
class GuiaSeccionResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = GuiaSeccion::class;

    protected static ?string $slug = 'guia';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 8;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Portal;
    }

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    protected static function referencias(): array
    {
        return ['unidad_id' => Unidad::class];
    }

    public static function getModelLabel(): string
    {
        return __('portal.guia_panel.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('portal.guia_panel.titulo');
    }

    public static function form(Schema $schema): Schema
    {
        $esPrincipal = fn (Get $get, string $idioma): bool => Propiedad::query()->whereKey($get('propiedad_id'))->value('idioma') === $idioma;

        return $schema->components([
            Section::make()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad()->live(),
                Select::make('unidad_id')->label(__('portal.guia_panel.unidad'))->helperText(__('portal.guia_panel.unidad_ayuda'))->native(false)->placeholder(__('portal.guia_panel.todas'))
                    ->options(fn (Get $get): array => Unidad::query()->where('propiedad_id', (int) $get('propiedad_id'))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Select::make('tipo')->label(__('portal.guia_panel.tipo'))->required()->native(false)->options(TipoSeccionGuia::opciones())->default(TipoSeccionGuia::Otro->value),
                Select::make('momento')->label(__('portal.guia_panel.momento'))->helperText(__('portal.guia_panel.momento_ayuda'))->required()->native(false)->options(MomentoGuia::opciones())->default(MomentoGuia::Siempre->value),
                Toggle::make('solo_con_reserva')->label(__('portal.guia_panel.solo_con_reserva'))->helperText(__('portal.guia_panel.solo_con_reserva_ayuda'))->live(),
                Toggle::make('requiere_pago')->label(__('portal.guia_panel.requiere_pago'))->helperText(__('portal.guia_panel.requiere_pago_ayuda')),
                TextInput::make('orden')->label(__('ui.unidades.orden'))->numeric()->integer()->default(0),
                Toggle::make('activa')->label(__('ui.tarifas.activa'))->default(true),
            ]),
            Section::make(__('portal.guia_panel.contenido'))->description(__('portal.guia_panel.contenido_ayuda'))->schema([
                Tabs::make('idiomas')->tabs(array_map(fn (string $idioma, string $nombre): Tab => Tab::make($nombre)->schema([
                    TextInput::make('titulo.'.$idioma)->label(__('portal.guia_panel.titulo_campo'))->maxLength(150),
                    Textarea::make('cuerpo.'.$idioma)->label(__('portal.guia_panel.cuerpo'))->rows(6)->maxLength(5000)->required(fn (Get $get): bool => $esPrincipal($get, $idioma)),
                ]), array_keys(Formato::idiomas()), array_values(Formato::idiomas()))),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['unidad', 'propiedad']))
            ->defaultSort('orden')
            ->reorderable('orden')
            ->columns([
                TextColumn::make('tipo')->label(__('portal.guia_panel.tipo'))->formatStateUsing(fn (TipoSeccionGuia $state): string => $state->etiqueta()),
                TextColumn::make('titulo')->label(__('portal.guia_panel.titulo_campo'))->state(fn (GuiaSeccion $record): string => (string) Textos::elegir($record->titulo, app()->getLocale(), $record->propiedad->idioma)),
                TextColumn::make('unidad.nombre')->label(__('portal.guia_panel.unidad'))->placeholder(__('portal.guia_panel.todas')),
                TextColumn::make('momento')->label(__('portal.guia_panel.momento'))->formatStateUsing(fn (MomentoGuia $state): string => $state->etiqueta()),
                IconColumn::make('solo_con_reserva')->label(__('portal.guia_panel.privada'))->boolean(),
                IconColumn::make('activa')->label(__('ui.tarifas.activa'))->boolean(),
                self::columnaPropiedad(),
            ])
            ->emptyStateHeading(__('portal.guia_panel.vacio'))
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGuiaSecciones::route('/'),
            'create' => CreateGuiaSeccion::route('/create'),
            'edit' => EditGuiaSeccion::route('/{record}/edit'),
        ];
    }
}
