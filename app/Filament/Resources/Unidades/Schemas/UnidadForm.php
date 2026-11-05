<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades\Schemas;

use App\Camping\AtributosLugar;
use App\Enums\TipoLugar;
use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class UnidadForm
{
    private static function unidadLargo(mixed $propiedadId): string
    {
        $propiedad = blank($propiedadId) ? null : Propiedad::query()->find((int) $propiedadId);

        return $propiedad === null ? 'm' : AtributosLugar::unidadLargo($propiedad);
    }

    /**
     * Las que se eligieron en la ficha, solo de su propiedad. La principal no hace falta repetirla.
     *
     * @param  array<int, mixed>  $tarifas
     */
    public static function guardarOtrasTarifas(Unidad $unidad, array $tarifas): void
    {
        $unidad->otrasTarifas()->sync(Tarifa::query()->where('propiedad_id', $unidad->propiedad_id)->whereIn('id', array_map('intval', $tarifas))->whereKeyNot((int) $unidad->tarifa_id)->pluck('id')->all());
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', UnidadResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(function (): ?int {
                            $permitidas = UnidadResource::propiedadesGestionables();

                            return count($permitidas) === 1 ? $permitidas[0] : null;
                        })
                        ->afterStateUpdated(function (Set $set): void {
                            $set('tarifa_id', null);
                            $set('otras_tarifas', []);
                        })
                        ->disabledOn('edit'),
                    TextInput::make('nombre')->label(__('ui.unidades.nombre'))->helperText(__('ui.unidades.nombre_ayuda'))->required()->maxLength(100)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), UnidadResource::propiedadesGestionables()))),
                    TextInput::make('capacidad')->label(__('ui.unidades.capacidad'))->required()->numeric()->minValue(1)->maxValue(1000)->default(2),
                    Select::make('tarifa_id')->label(__('ui.unidades.tarifa'))->helperText(__('ui.unidades.tarifa_ayuda'))->native(false)
                        ->options(fn (Get $get): array => blank($get('propiedad_id')) ? [] : Tarifa::query()->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), UnidadResource::propiedadesGestionables()))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    Select::make('otras_tarifas')->label(__('ui.unidades.otras_tarifas'))->helperText(__('ui.unidades.otras_tarifas_ayuda'))->multiple()->native(false)
                        ->options(fn (Get $get): array => blank($get('propiedad_id')) ? [] : Tarifa::query()->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), UnidadResource::propiedadesGestionables()))->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->formatStateUsing(fn (?Unidad $record): array => $record === null ? [] : $record->otrasTarifas()->pluck('tarifas.id')->map(fn (mixed $id): int => (int) $id)->all())
                        ->columnSpanFull(),
                    TextInput::make('piso')->label(__('ui.unidades.piso'))->maxLength(50),
                    TextInput::make('edificio')->label(__('ui.unidades.edificio'))->maxLength(50),
                    TextInput::make('orden')->label(__('ui.unidades.orden'))->helperText(__('ui.unidades.orden_ayuda'))->numeric()->minValue(-100000)->maxValue(100000)->default(0),
                    Toggle::make('activa')->label(__('ui.unidades.activa'))->helperText(__('ui.unidades.activa_ayuda'))->default(true),
                    Textarea::make('notas')->label(__('ui.unidades.notas'))->rows(3)->maxLength(5000)->columnSpanFull(),
                ]),
            Section::make(__('camping.atributos.titulo'))->description(__('camping.atributos.ayuda'))->columnSpanFull()->collapsible()
                ->visible(fn (): bool => Modulos::activo(Modulo::Camping))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('atributos.tipo')->label(__('camping.atributos.tipo'))->native(false)->options(TipoLugar::opciones()),
                    Select::make('atributos.acceso')->label(__('camping.atributos.acceso'))->native(false)->options(['atravesado' => __('camping.atributos.acceso_atravesado'), 'reversa' => __('camping.atributos.acceso_reversa')]),
                    TextInput::make('atributos.largo_maximo')->label(fn (Get $get): string => __('camping.atributos.largo', ['unidad' => self::unidadLargo($get('propiedad_id'))]))->numeric()->minValue(0)->maxValue(1000)->step('any'),
                    TextInput::make('atributos.amperes')->label(__('camping.atributos.amperes'))->numeric()->integer()->minValue(0)->maxValue(1000)->visible(fn (Get $get): bool => (bool) $get('atributos.luz')),
                    Toggle::make('atributos.luz')->label(__('camping.atributos.luz'))->live(),
                    Toggle::make('atributos.agua')->label(__('camping.atributos.agua')),
                    Toggle::make('atributos.drenaje')->label(__('camping.atributos.drenaje')),
                    Toggle::make('atributos.mascotas')->label(__('camping.atributos.mascotas')),
                    Toggle::make('atributos.accesible')->label(__('camping.atributos.accesible')),
                ]),
        ]);
    }
}
