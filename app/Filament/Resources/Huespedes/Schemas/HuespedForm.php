<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Schemas;

use App\Enums\TipoContacto;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\ColorTipoCliente;
use App\Models\Propiedad;
use App\Paises\CatalogoPaises;
use App\Paises\CatalogoRegiones;
use App\Support\Contactos;
use App\Support\Formato;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ficha de un huésped de una propiedad independiente.
 */
class HuespedForm
{
    /**
     * Lo escrito en la ficha; si no dice, se supone el de la propiedad.
     */
    private static function paisDelDomicilio(Get $get): ?string
    {
        $escrito = trim((string) $get('domicilio_pais_texto'));

        return $escrito !== '' ? CatalogoPaises::codigoDe($escrito) : Propiedad::query()->whereKey((int) $get('propiedad_id'))->value('pais');
    }

    /**
     * @return Collection<int, ColorTipoCliente>
     */
    private static function tiposDeCliente(mixed $propiedadId): Collection
    {
        return ColorTipoCliente::query()->where('propiedad_id', (int) $propiedadId)->orderBy('campo')->orderBy('valor')->get();
    }

    public static function configure(Schema $schema): Schema
    {
        $normalizar = function (array $data): array {
            $data['valor_normalizado'] = Contactos::normalizar((string) ($data['tipo'] ?? ''), (string) ($data['valor'] ?? ''));

            return $data;
        };

        return $schema->components([
            Section::make(__('ui.huespedes.datos_personales'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)
                        ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(function (): ?int {
                            $permitidas = ReservaResource::propiedadesGestionables();

                            return count($permitidas) === 1 ? $permitidas[0] : null;
                        })
                        ->disabledOn('edit'),
                    TextInput::make('nombre')->label(__('ui.huespedes.nombre'))->required()->maxLength(100),
                    TextInput::make('apellido1')->label(__('ui.huespedes.apellido1'))->maxLength(100),
                    TextInput::make('apellido2')->label(__('ui.huespedes.apellido2'))->maxLength(100),
                    Select::make('idioma')->label(__('ui.huespedes.idioma'))->options(Formato::idiomas())->native(false),
                    TextInput::make('nacionalidad_texto')->label(__('ui.huespedes.nacionalidad'))->maxLength(100),
                    DatePicker::make('fecha_nacimiento')->label(__('ui.huespedes.nacimiento')),
                    TextInput::make('lugar_nacimiento')->label(__('ui.huespedes.lugar_nacimiento'))->maxLength(255),
                ]),
            Section::make(__('ui.huespedes.contactos'))->columnSpanFull()
                ->schema([
                    Repeater::make('contactos')->label(__('ui.huespedes.contactos'))->hiddenLabel()->relationship()
                        ->orderColumn('orden')
                        ->columns(['default' => 1, 'md' => 2])
                        ->defaultItems(0)
                        ->addActionLabel(__('ui.huespedes.anadir_contacto'))
                        ->mutateRelationshipDataBeforeCreateUsing($normalizar)
                        ->mutateRelationshipDataBeforeSaveUsing($normalizar)
                        ->schema([
                            Select::make('tipo')->label(__('ui.huespedes.tipo_contacto'))->options(TipoContacto::opciones())->required()->native(false)->live()
                                ->default(TipoContacto::Telefono->value),
                            TextInput::make('valor')->label(__('ui.huespedes.valor_contacto'))->required()->maxLength(255)
                                ->rules(fn (Get $get): array => ($get('tipo') instanceof TipoContacto ? $get('tipo')->value : $get('tipo')) === TipoContacto::Correo->value ? ['email'] : []),
                        ]),
                ]),
            Section::make(__('ui.huespedes.documento'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                ->collapsible()
                ->schema([
                    TextInput::make('documento_tipo_texto')->label(__('ui.huespedes.documento_tipo'))->maxLength(100),
                    TextInput::make('documento_numero')->label(__('ui.huespedes.documento_numero'))->helperText(__('ui.huespedes.documento_ayuda'))
                        ->maxLength(100)->autocomplete('off')
                        // Vacío al editar = conservar el actual; nunca se muestra completo (D-022).
                        ->formatStateUsing(fn (): string => '')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    TextInput::make('id_fiscal_personal')->label(__('ui.huespedes.fiscal'))->maxLength(50),
                ]),
            Section::make(__('ui.huespedes.domicilio'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                ->collapsible()
                ->schema([
                    TextInput::make('domicilio_calle')->label(__('ui.huespedes.calle'))->maxLength(150),
                    TextInput::make('domicilio_numero')->label(__('ui.huespedes.numero'))->maxLength(30),
                    TextInput::make('domicilio_codigo_postal')->label(__('ui.huespedes.codigo_postal'))->maxLength(30),
                    TextInput::make('domicilio_ciudad')->label(__('ui.huespedes.ciudad'))->maxLength(100),
                    TextInput::make('domicilio_region')->label(__('ui.huespedes.region'))->maxLength(100)
                        ->datalist(fn (Get $get): array => array_values(CatalogoRegiones::de(self::paisDelDomicilio($get)))),
                    TextInput::make('domicilio_pais_texto')->label(__('ui.huespedes.pais'))->maxLength(100)->live(onBlur: true),
                ]),
            // Lo que colorea el calendario (D-088); en una subordinada viene de la principal.
            Section::make(__('herramientas.tipo_cliente.titulo'))->description(__('herramientas.tipo_cliente.ayuda'))->columnSpanFull()
                ->collapsible()
                ->schema([
                    Repeater::make('campos')->label(__('herramientas.tipo_cliente.titulo'))->hiddenLabel()->relationship()
                        ->defaultItems(0)->columns(['default' => 1, 'md' => 2])->addActionLabel(__('herramientas.tipo_cliente.agregar'))
                        ->schema([
                            TextInput::make('campo')->label(__('plano.colores.campo'))->required()->maxLength(100)->live(onBlur: true)
                                ->datalist(fn (Get $get): array => self::tiposDeCliente($get('../../propiedad_id'))->pluck('campo')->unique()->values()->all()),
                            TextInput::make('valor')->label(__('plano.colores.valor'))->required()->maxLength(255)
                                ->datalist(fn (Get $get): array => self::tiposDeCliente($get('../../propiedad_id'))->where('campo', $get('campo'))->pluck('valor')->unique()->values()->all()),
                        ]),
                ]),
            Section::make(__('ui.huespedes.notas'))->columnSpanFull()
                ->collapsible()
                ->schema([
                    Textarea::make('notas')->label(__('ui.huespedes.notas'))->hiddenLabel()->rows(3)->maxLength(5000),
                ]),
        ]);
    }
}
