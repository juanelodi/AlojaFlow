<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Schemas;

use App\Enums\MetodoPeticion;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Paises\CatalogoPaises;
use App\Support\Formato;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PropiedadForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('ui.propiedades.modo'))->columnSpanFull()
                ->schema([
                    // Se elige al crearla: pasar de una forma a la otra será una migración aparte.
                    Radio::make('modo')->label(__('ui.propiedades.modo'))->hiddenLabel()->required()->live()
                        ->options(ModoPropiedad::opciones())
                        ->descriptions([
                            ModoPropiedad::Nativa->value => __('ui.propiedades.modo_independiente_ayuda'),
                            ModoPropiedad::Subordinada->value => __('ui.propiedades.modo_subordinada_ayuda'),
                        ])
                        ->disabledOn('edit'),
                ]),
            Section::make(__('ui.propiedades.datos'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('nombre')->label(__('ui.propiedades.nombre'))->required()->maxLength(150)->live(onBlur: true)
                        ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                            if (blank($get('codigo'))) {
                                $set('codigo', Str::limit(Str::slug((string) $state), 40, ''));
                            }
                        }),
                    Select::make('pais')->label(__('ui.propiedades.pais'))->helperText(fn (string $operation): ?string => $operation === 'create' ? __('ui.propiedades.pais_ayuda') : null)
                        ->options(fn (): array => CatalogoPaises::opciones())->searchable()->native(false)->default('MX')->live()
                        ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                            // Solo al crear: en una propiedad con reservas, cambiar la moneda sin avisar sería un error grave.
                            if ($operation === 'create' && filled($state)) {
                                foreach (CatalogoPaises::valoresDe($state) as $campo => $valor) {
                                    $set($campo, $valor);
                                }
                            }
                        }),
                    Select::make('idioma')->label(__('ui.propiedades.idioma'))->options(Formato::idiomas())->default('es')->required()->native(false),
                    ColorPicker::make('color')->label(__('ui.propiedades.color'))->default('#0f766e')->regex('/^#[0-9a-fA-F]{6}$/'),
                    Toggle::make('activa')->label(__('ui.propiedades.activa'))->default(true),
                ]),
            Section::make(__('ui.propiedades.avanzado'))->description(__('ui.propiedades.avanzado_ayuda'))->columnSpanFull()->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('codigo')->label(__('ui.propiedades.codigo'))->helperText(__('ui.propiedades.codigo_ayuda'))
                        ->required()->maxLength(40)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('paquete_pais')->label(__('ui.propiedades.paquete_pais'))->helperText(__('ui.propiedades.paquete_pais_ayuda'))
                        ->options(PaquetePais::opciones())->default(PaquetePais::Mexico->value)->required()->native(false),
                    Select::make('zona_horaria')->label(__('ui.propiedades.zona_horaria'))
                        ->options(Formato::zonasHorarias())->searchable()->required()->default('America/Mexico_City'),
                    TextInput::make('moneda')->label(__('ui.propiedades.moneda'))->required()->length(3)->regex('/^[A-Za-z]{3}$/')
                        ->dehydrateStateUsing(fn (string $state): string => strtoupper($state))->default('MXN'),
                ]),
            Section::make(__('ui.propiedades.principal'))->columnSpanFull()
                ->description(__('ui.propiedades.principal_ayuda'))
                ->relationship('principal')
                ->visible(fn (Get $get): bool => self::modo($get) === ModoPropiedad::Subordinada)
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
                    Select::make('metodo')->label(__('ui.propiedades.metodo'))->helperText(__('ui.propiedades.metodo_ayuda'))->options(MetodoPeticion::opciones())
                        ->default(MetodoPeticion::Post->value)->required()->native(false),
                    Toggle::make('compresion')->label(__('ui.propiedades.compresion'))->default(true),
                    Toggle::make('permitir_red_local')->label(__('ui.propiedades.red_local'))->helperText(__('ui.propiedades.red_local_ayuda'))
                        ->default(false),
                ]),
        ]);
    }

    private static function modo(Get $get): ?ModoPropiedad
    {
        $modo = $get('modo');

        return $modo instanceof ModoPropiedad ? $modo : ModoPropiedad::tryFrom((string) $modo);
    }
}
