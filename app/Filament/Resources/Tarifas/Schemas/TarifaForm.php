<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Schemas;

use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Propiedad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

class TarifaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', TarifaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(function (): ?int {
                            $permitidas = TarifaResource::propiedadesGestionables();

                            return count($permitidas) === 1 ? $permitidas[0] : null;
                        })
                        ->disabledOn('edit'),
                    TextInput::make('nombre')->label(__('ui.tarifas.nombre'))->required()->maxLength(100)
                        ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('propiedad_id', (int) $get('propiedad_id'))),
                    CampoDinero::make('precio_noche')->label(__('ui.tarifas.precio_base'))->helperText(__('ui.tarifas.precio_base_ayuda'))->required(),
                    Toggle::make('activa')->label(__('ui.tarifas.activa'))->helperText(__('ui.tarifas.activa_ayuda'))->default(true),
                ]),
            Section::make(__('ui.tarifas.temporadas'))->columnSpanFull()
                ->description(__('ui.tarifas.temporadas_ayuda'))
                ->schema([
                    Repeater::make('temporadas')->hiddenLabel()->relationship()
                        ->defaultItems(0)
                        ->addActionLabel(__('ui.tarifas.anadir_temporada'))
                        ->columns(['default' => 1, 'md' => 4])
                        ->schema([
                            TextInput::make('nombre')->label(__('ui.tarifas.temporada'))->required()->maxLength(100),
                            DatePicker::make('desde')->label(__('ui.tarifas.desde'))->required(),
                            DatePicker::make('hasta')->label(__('ui.tarifas.hasta'))->required()->afterOrEqual('desde'),
                            CampoDinero::make('precio_noche', '../../propiedad_id')->label(__('ui.tarifas.precio_noche'))->required(),
                        ]),
                ]),
        ]);
    }
}
