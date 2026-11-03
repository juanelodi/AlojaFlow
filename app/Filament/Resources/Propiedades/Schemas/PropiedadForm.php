<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Schemas;

use App\Enums\MetodoPeticion;
use App\Enums\PaquetePais;
use App\Support\Formato;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PropiedadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('ui.propiedades.datos'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('nombre')->label(__('ui.propiedades.nombre'))->required()->maxLength(150),
                    TextInput::make('codigo')->label(__('ui.propiedades.codigo'))->helperText(__('ui.propiedades.codigo_ayuda'))
                        ->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('paquete_pais')->label(__('ui.propiedades.paquete_pais'))
                        ->options(PaquetePais::opciones())->default(PaquetePais::Mexico->value)->required()->native(false),
                    TextInput::make('pais')->label(__('ui.propiedades.pais'))->maxLength(2)->regex('/^[A-Za-z]{2}$/')
                        ->dehydrateStateUsing(fn (?string $state): ?string => $state === null ? null : strtoupper($state))->default('MX'),
                    Select::make('zona_horaria')->label(__('ui.propiedades.zona_horaria'))
                        ->options(Formato::zonasHorarias())->searchable()->required()->default('America/Mexico_City'),
                    TextInput::make('moneda')->label(__('ui.propiedades.moneda'))->required()->length(3)->regex('/^[A-Za-z]{3}$/')
                        ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))->default('MXN'),
                    Select::make('idioma')->label(__('ui.propiedades.idioma'))->options(Formato::idiomas())->default('es')->required()->native(false),
                    ColorPicker::make('color')->label(__('ui.propiedades.color'))->default('#0f766e')->regex('/^#[0-9a-fA-F]{6}$/'),
                    Toggle::make('activa')->label(__('ui.propiedades.activa'))->default(true),
                ]),
            Section::make(__('ui.propiedades.principal'))->columnSpanFull()
                ->description(__('ui.propiedades.principal_ayuda'))
                ->relationship('principal')
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('url_base')->label(__('ui.propiedades.url_base'))->helperText(__('ui.propiedades.url_base_ayuda'))
                        ->required()->maxLength(255)->url()->regex('#^https?://[^?\#]+/$#')->columnSpanFull(),
                    TextInput::make('usuario')->label(__('ui.propiedades.usuario'))->maxLength(100)->autocomplete('off'),
                    TextInput::make('contrasena')->label(__('ui.propiedades.contrasena'))->helperText(__('ui.propiedades.contrasena_ayuda'))
                        ->password()->revealable()->maxLength(200)->autocomplete('new-password')
                        // Vacía al editar = conservar la actual; nunca se muestra (regla 13).
                        ->formatStateUsing(fn (): string => '')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    Select::make('frecuencia_minutos')->label(__('ui.propiedades.frecuencia'))->required()->native(false)->default(30)
                        ->options([
                            15 => __('ui.propiedades.cada_minutos', ['minutos' => 15]),
                            30 => __('ui.propiedades.cada_minutos', ['minutos' => 30]),
                            60 => __('ui.propiedades.cada_horas', ['horas' => 1]),
                            120 => __('ui.propiedades.cada_horas', ['horas' => 2]),
                            360 => __('ui.propiedades.cada_horas', ['horas' => 6]),
                            720 => __('ui.propiedades.cada_horas', ['horas' => 12]),
                            1440 => __('ui.propiedades.cada_horas', ['horas' => 24]),
                        ]),
                    Select::make('metodo')->label(__('ui.propiedades.metodo'))->options(MetodoPeticion::opciones())
                        ->default(MetodoPeticion::Post->value)->required()->native(false),
                    Toggle::make('compresion')->label(__('ui.propiedades.compresion'))->default(true),
                    Toggle::make('permitir_red_local')->label(__('ui.propiedades.red_local'))->helperText(__('ui.propiedades.red_local_ayuda'))
                        ->default(false),
                ]),
        ]);
    }
}
