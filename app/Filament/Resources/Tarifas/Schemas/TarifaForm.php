<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas\Schemas;

use App\Enums\ModoTarifa;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Propiedad;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
        $porNoche = fn (Get $get): bool => self::modo($get) === ModoTarifa::PorNoche;

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
                    Select::make('modo')->label(__('ui.tarifas.modo'))->helperText(__('ui.tarifas.modo_ayuda'))->options(ModoTarifa::opciones())->required()->native(false)->live()
                        ->default(ModoTarifa::PorNoche->value),
                    CampoDinero::make('precio_noche')
                        ->label(fn (Get $get): string => $porNoche($get) ? __('ui.tarifas.precio_base') : __('ui.tarifas.precio_persona'))
                        ->helperText(__('ui.tarifas.precio_base_ayuda'))->required(),
                    TextInput::make('personas_incluidas')->label(__('ui.tarifas.personas_incluidas'))->helperText(__('ui.tarifas.personas_incluidas_ayuda'))->numeric()->minValue(1)->default(2)
                        ->required($porNoche)->visible($porNoche),
                    CampoDinero::make('precio_persona_extra')->label(__('ui.tarifas.persona_extra'))->default(0)->visible($porNoche),
                    CampoDinero::make('precio_fin_semana')->label(__('ui.tarifas.fin_semana'))->helperText(__('ui.tarifas.fin_semana_ayuda'))
                        ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get('propiedad_id')))),
                    TextInput::make('estancia_minima')->label(__('ui.tarifas.estancia_minima'))->numeric()->minValue(1)->default(1)->required(),
                    Toggle::make('activa')->label(__('ui.tarifas.activa'))->helperText(__('ui.tarifas.activa_ayuda'))->default(true),
                    Toggle::make('visible_motor')->label(__('ui.tarifas.visible_motor'))->default(true),
                    Textarea::make('descripcion_publica')->label(__('ui.tarifas.descripcion_publica'))->helperText(__('ui.tarifas.descripcion_publica_ayuda'))->rows(2)->maxLength(1000)->columnSpanFull(),
                ]),
            Section::make(__('ui.tarifas.temporadas'))->columnSpanFull()
                ->description(__('ui.tarifas.temporadas_ayuda'))
                ->schema([
                    Repeater::make('temporadas')->hiddenLabel()->relationship()
                        ->defaultItems(0)
                        ->addActionLabel(__('ui.tarifas.anadir_temporada'))
                        ->columns(['default' => 1, 'md' => 3])
                        ->schema([
                            TextInput::make('nombre')->label(__('ui.tarifas.temporada'))->required()->maxLength(100),
                            DatePicker::make('desde')->label(__('ui.tarifas.desde'))->required(),
                            DatePicker::make('hasta')->label(__('ui.tarifas.hasta'))->required()->afterOrEqual('desde'),
                            CampoDinero::make('precio_noche', '../../propiedad_id')->label(__('ui.tarifas.precio_noche'))->required(),
                            CampoDinero::make('precio_fin_semana', '../../propiedad_id')->label(__('ui.tarifas.fin_semana'))
                                ->dehydrateStateUsing(fn (mixed $state, Get $get): ?int => blank($state) ? null : \App\Support\Dinero::aUnidadMenor((string) $state, CampoDinero::moneda($get('../../propiedad_id')))),
                            TextInput::make('estancia_minima')->label(__('ui.tarifas.estancia_minima'))->numeric()->minValue(1),
                        ]),
                ]),
        ]);
    }

    private static function modo(Get $get): ModoTarifa
    {
        $modo = $get('modo');

        return $modo instanceof ModoTarifa ? $modo : (ModoTarifa::tryFrom((string) $modo) ?? ModoTarifa::PorNoche);
    }
}
