<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Omisiones;

use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Omisiones\Pages\CreateOmision;
use App\Filament\Resources\Omisiones\Pages\EditOmision;
use App\Filament\Resources\Omisiones\Pages\ListOmisiones;
use App\Models\Huesped;
use App\Models\Omision;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
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
 * Clientes omitidos: el área donde se elige, con casillas, en qué lugares no sale cada uno (D-074). Datos propios: también en subordinadas.
 */
class OmisionResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Omision::class;

    protected static ?string $slug = 'clientes-omitidos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEyeSlash;

    protected static ?int $navigationSort = 6;

    protected static function permiso(): string
    {
        return Acceso::HUESPEDES;
    }

    /**
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    protected static function referencias(): array
    {
        return ['huesped_id' => Huesped::class];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.reservas');
    }

    public static function getModelLabel(): string
    {
        return __('equipo.omisiones.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('equipo.omisiones.modelos');
    }

    public static function form(Schema $schema): Schema
    {
        $porHuesped = fn (Get $get): bool => $get('por') !== 'tipo';

        return $schema->components([
            Section::make()->columnSpanFull()->description(__('equipo.omisiones.ayuda'))->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                Radio::make('por')->label(__('equipo.omisiones.por'))->live()->dehydrated(false)
                    ->options(['huesped' => __('equipo.omisiones.por_huesped'), 'tipo' => __('equipo.omisiones.por_tipo')])
                    ->default('huesped')
                    ->formatStateUsing(fn (?Omision $record): string => $record?->campo !== null ? 'tipo' : 'huesped'),
                Select::make('huesped_id')->label(__('ui.comun.huesped'))->native(false)->searchable()->columnSpanFull()
                    ->visible($porHuesped)->required($porHuesped)
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', self::propiedadDe($get)))
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => Huesped::query()->where('propiedad_id', self::propiedadDe($get))
                        ->where(fn (Builder $query): Builder => $query->where('nombre', 'like', "%{$search}%")->orWhere('apellido1', 'like', "%{$search}%"))
                        ->orderBy('apellido1')->limit(15)->get()->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                    ->getOptionLabelUsing(fn (mixed $value): ?string => Huesped::query()->find($value)?->nombreCompleto()),
                TextInput::make('campo')->label(__('equipo.omisiones.campo'))->helperText(__('equipo.omisiones.campo_ayuda'))->maxLength(100)
                    ->visible(fn (Get $get): bool => ! $porHuesped($get))->required(fn (Get $get): bool => ! $porHuesped($get)),
                TextInput::make('valor')->label(__('equipo.omisiones.valor'))->maxLength(255)
                    ->visible(fn (Get $get): bool => ! $porHuesped($get))->required(fn (Get $get): bool => ! $porHuesped($get)),
                Section::make(__('equipo.omisiones.donde'))->columnSpanFull()->schema([
                    Toggle::make('en_aviso')->label(__('equipo.omisiones.en_aviso'))->helperText(__('equipo.omisiones.en_aviso_ayuda'))->default(true),
                    Toggle::make('en_informes')->label(__('equipo.omisiones.en_informes'))->helperText(__('equipo.omisiones.en_informes_ayuda'))->default(false),
                    Toggle::make('en_mensajes')->label(__('equipo.omisiones.en_mensajes'))->helperText(__('equipo.omisiones.en_mensajes_ayuda'))->default(false),
                ]),
                TextInput::make('motivo')->label(__('equipo.omisiones.motivo'))->maxLength(255)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['huesped', 'propiedad']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('quien')->label(__('equipo.omisiones.quien'))
                    ->state(fn (Omision $record): string => $record->huesped?->nombreCompleto() ?? $record->campo.': '.$record->valor),
                self::columnaPropiedad(),
                IconColumn::make('en_aviso')->label(__('equipo.omisiones.en_aviso'))->boolean(),
                IconColumn::make('en_informes')->label(__('equipo.omisiones.en_informes'))->boolean(),
                IconColumn::make('en_mensajes')->label(__('equipo.omisiones.en_mensajes'))->boolean(),
                TextColumn::make('motivo')->label(__('equipo.omisiones.motivo'))->placeholder('—')->limit(60),
            ])
            ->emptyStateHeading(__('equipo.omisiones.vacio'))
            ->emptyStateDescription(__('equipo.omisiones.ayuda'))
            ->recordActions([EditAction::make()->visible(fn (Omision $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOmisiones::route('/'),
            'create' => CreateOmision::route('/create'),
            'edit' => EditOmision::route('/{record}/edit'),
        ];
    }
}
