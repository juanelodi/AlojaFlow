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
use App\Enums\TipoPrincipal;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Paises\CatalogoPaises;
use App\Paises\CatalogoRegiones;
use App\Sincronizacion\AlojaFlow\CodigoConexion;
use App\Support\Formato;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
            // Sin el módulo de subordinación solo hay una forma de crearla; CreatePropiedad la pone.
            Section::make(__('ui.propiedades.modo'))->columnSpanFull()
                ->visible(fn (string $operation): bool => $operation === 'edit' || Modulos::activo(Modulo::Subordinacion))
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
                            // La región de otro país no vale en este.
                            $set('subdivision', null);
                        }),
                    Select::make('subdivision')->label(__('herramientas.region.campo'))->helperText(__('herramientas.region.ayuda'))->searchable()->native(false)
                        ->options(fn (Get $get): array => CatalogoRegiones::de($get('pais')))
                        ->visible(fn (Get $get): bool => CatalogoRegiones::de($get('pais')) !== []),
                    TextInput::make('subdivision')->label(__('herramientas.region.campo'))->maxLength(100)
                        ->visible(fn (Get $get): bool => CatalogoRegiones::de($get('pais')) === []),
                    Select::make('idioma')->label(__('ui.propiedades.idioma'))->options(Formato::idiomas())->default('es')->required()->native(false),
                    ColorPicker::make('color')->label(__('ui.propiedades.color'))->default('#0f766e')->regex('/^#[0-9a-fA-F]{6}$/'),
                    Toggle::make('activa')->label(__('ui.propiedades.activa'))->default(true),
                ]),
            Section::make(__('herramientas.registro.responsables'))->description(__('herramientas.registro.responsables_ayuda'))->columnSpanFull()->collapsed()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('responsable')->label(__('herramientas.registro.responsable'))->maxLength(150),
                    TextInput::make('autoridad_quejas')->label(__('herramientas.registro.autoridad_quejas'))->helperText(__('herramientas.registro.autoridad_quejas_ayuda'))->maxLength(255),
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
                ->mutateRelationshipDataBeforeFillUsing(fn (array $data): array => self::conDiasEnLista($data))
                ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => self::conDiasEnMascara(self::conCodigoLeido($data)))
                ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => self::conDiasEnMascara(self::conCodigoLeido($data)))
                ->visible(fn (Get $get): bool => self::modo($get) === ModoPropiedad::Subordinada)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Radio::make('tipo')->label(__('sincronizacion.formulario.tipo'))->required()->live()->columnSpanFull()
                        ->options(TipoPrincipal::opciones())->default(TipoPrincipal::HotelDruid->value)
                        // Cambiar de sistema a una principal con datos sería otra propiedad.
                        ->disabledOn('edit')->dehydrated(fn (string $operation): bool => $operation === 'create'),
                    Textarea::make('codigo_conexion')->label(__('sincronizacion.formulario.codigo'))->helperText(__('sincronizacion.formulario.codigo_ayuda'))->rows(3)->columnSpanFull()
                        ->visible(fn (Get $get): bool => self::esDeAlojaFlow($get))
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->formatStateUsing(fn (): string => '')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (filled($value) && CodigoConexion::leer((string) $value) === null) {
                                $fail(__('sincronizacion.formulario.codigo_invalido'));
                            }
                        }),
                    TextInput::make('url_base')->label(__('ui.propiedades.url_base'))->helperText(__('ui.propiedades.url_base_ayuda'))
                        ->visible(fn (Get $get): bool => ! self::esDeAlojaFlow($get))
                        ->required()->maxLength(255)->url()->regex('#^https?://[^?\#]+/$#')->columnSpanFull(),
                    TextInput::make('usuario')->label(__('ui.propiedades.usuario'))->maxLength(100)->autocomplete('off')
                        ->visible(fn (Get $get): bool => ! self::esDeAlojaFlow($get)),
                    TextInput::make('contrasena')->label(__('ui.propiedades.contrasena'))->helperText(__('ui.propiedades.contrasena_ayuda'))
                        ->visible(fn (Get $get): bool => ! self::esDeAlojaFlow($get))
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
                    CheckboxList::make('dias_semana')->label(__('herramientas.franja.dias'))->helperText(__('herramientas.franja.dias_ayuda'))->columnSpanFull()->columns(['default' => 2, 'md' => 4, 'lg' => 7])
                        ->options(fn (): array => Formato::diasDeLaSemana()),
                    TimePicker::make('franja_desde')->label(__('herramientas.franja.desde'))->helperText(__('herramientas.franja.ayuda'))->seconds(false)->requiredWith('franja_hasta'),
                    TimePicker::make('franja_hasta')->label(__('herramientas.franja.hasta'))->seconds(false)->requiredWith('franja_desde'),
                    Select::make('metodo')->label(__('ui.propiedades.metodo'))->helperText(__('ui.propiedades.metodo_ayuda'))->options(MetodoPeticion::opciones())
                        ->visible(fn (Get $get): bool => ! self::esDeAlojaFlow($get))
                        ->default(MetodoPeticion::Post->value)->required()->native(false),
                    Toggle::make('compresion')->label(__('ui.propiedades.compresion'))->default(true)
                        ->visible(fn (Get $get): bool => ! self::esDeAlojaFlow($get)),
                    Toggle::make('permitir_red_local')->label(__('ui.propiedades.red_local'))->helperText(__('ui.propiedades.red_local_ayuda'))
                        ->default(false),
                ]),
        ]);
    }

    private static function esDeAlojaFlow(Get $get): bool
    {
        $tipo = $get('tipo');

        return ($tipo instanceof TipoPrincipal ? $tipo : TipoPrincipal::tryFrom((string) $tipo)) === TipoPrincipal::AlojaFlow;
    }

    /**
     * En el formulario, los días son una lista; en la base, una máscara. Se convierte aquí porque las casillas envuelven su estado en una lista antes de
     * cualquier otro paso, y 5 (lunes y miércoles) ya no se distinguiría del viernes.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function conDiasEnLista(array $data): array
    {
        $mascara = (int) ($data['dias_semana'] ?? 0);
        $data['dias_semana'] = array_values(array_filter(range(1, 7), fn (int $dia): bool => ($mascara & (1 << ($dia - 1))) !== 0));

        return $data;
    }

    /**
     * Todos los días o ninguno es lo mismo: sin límite. Nadie quiere una principal que no se copie nunca.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function conDiasEnMascara(array $data): array
    {
        $dias = (array) ($data['dias_semana'] ?? []);
        $data['dias_semana'] = count($dias) % 7 === 0 ? null : array_sum(array_map(fn (mixed $dia): int => 1 << ((int) $dia - 1), $dias));

        return $data;
    }

    /**
     * El código se separa en la dirección y el token, que son los que se guardan. Vacío al editar = conservar los actuales.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function conCodigoLeido(array $data): array
    {
        $leido = isset($data['codigo_conexion']) ? CodigoConexion::leer((string) $data['codigo_conexion']) : null;
        unset($data['codigo_conexion']);
        if ($leido !== null) {
            $data['url_base'] = $leido['url'];
            $data['contrasena'] = $leido['token'];
        }

        return $data;
    }

    private static function modo(Get $get): ?ModoPropiedad
    {
        $modo = $get('modo');

        return $modo instanceof ModoPropiedad ? $modo : ModoPropiedad::tryFrom((string) $modo);
    }
}
