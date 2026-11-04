<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades\Schemas;

use App\Filament\Resources\Unidades\UnidadResource;
use App\Models\Propiedad;
use App\Models\Tarifa;
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
                        ->afterStateUpdated(fn (Set $set) => $set('tarifa_id', null))
                        ->disabledOn('edit'),
                    TextInput::make('nombre')->label(__('ui.unidades.nombre'))->helperText(__('ui.unidades.nombre_ayuda'))->required()->maxLength(100)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), UnidadResource::propiedadesGestionables()))),
                    TextInput::make('capacidad')->label(__('ui.unidades.capacidad'))->required()->numeric()->minValue(1)->maxValue(1000)->default(2),
                    Select::make('tarifa_id')->label(__('ui.unidades.tarifa'))->helperText(__('ui.unidades.tarifa_ayuda'))->native(false)
                        ->options(fn (Get $get): array => blank($get('propiedad_id')) ? [] : Tarifa::query()->where('propiedad_id', Acceso::propiedadElegida($get('propiedad_id'), UnidadResource::propiedadesGestionables()))->orderBy('nombre')->pluck('nombre', 'id')->all()),
                    TextInput::make('piso')->label(__('ui.unidades.piso'))->maxLength(50),
                    TextInput::make('edificio')->label(__('ui.unidades.edificio'))->maxLength(50),
                    TextInput::make('orden')->label(__('ui.unidades.orden'))->helperText(__('ui.unidades.orden_ayuda'))->numeric()->minValue(-100000)->maxValue(100000)->default(0),
                    Toggle::make('activa')->label(__('ui.unidades.activa'))->helperText(__('ui.unidades.activa_ayuda'))->default(true),
                    Textarea::make('notas')->label(__('ui.unidades.notas'))->rows(3)->maxLength(5000)->columnSpanFull(),
                ]),
        ]);
    }
}
