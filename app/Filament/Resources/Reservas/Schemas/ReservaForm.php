<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Schemas;

use App\Enums\EstadoReserva;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Filament\Campos\CampoDinero;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Reservas\Cotizacion;
use App\Reservas\Cotizador;
use App\Servicios\Disponibilidad;
use App\Support\Contactos;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Alta y cambio de reservas y bloqueos de una propiedad independiente. Las reglas de verdad están
 * en GestorReservas; aquí se guía a quien escribe: unidades ocupadas desactivadas y precio de la
 * tarifa ya puesto. El calendario y la disponibilidad traen datos por la dirección.
 */
class ReservaForm
{
    public static function configure(Schema $schema): Schema
    {
        $esReserva = fn (Get $get): bool => self::tipo($get) === TipoReserva::Reserva;
        $alCambiar = function (Get $get, Set $set, string $operation): void {
            if ($operation === 'create') {
                self::aplicarTarifa($get, $set);
            }
        };

        return $schema->components([
            Hidden::make('version'),
            Section::make(__('ui.reservas.estancia'))->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                ->schema([
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => self::propiedadInicial())
                        ->disabledOn('edit')
                        ->afterStateUpdated(function (Set $set): void {
                            $set('unidad_id', null);
                            $set('titular_id', null);
                            $set('total', null);
                        }),
                    Select::make('tipo')->label(__('ui.reservas.tipo'))->options(TipoReserva::opciones())->required()->native(false)->live()
                        ->default(TipoReserva::Reserva->value)
                        ->disabledOn('edit'),
                    Select::make('estado')->label(__('ui.comun.estado'))->required()->native(false)
                        ->options([
                            EstadoReserva::Confirmada->value => EstadoReserva::Confirmada->etiqueta(),
                            EstadoReserva::SinConfirmar->value => EstadoReserva::SinConfirmar->etiqueta(),
                        ])
                        ->default(EstadoReserva::Confirmada->value)
                        ->visible($esReserva),
                    DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required()->live(onBlur: true)
                        ->default(fn (): ?string => self::fechaDeLaDireccion('llegada'))
                        ->afterStateUpdated($alCambiar),
                    DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->live(onBlur: true)->after('llegada')
                        ->default(fn (): ?string => self::fechaDeLaDireccion('salida'))
                        ->afterStateUpdated($alCambiar),
                    TextInput::make('personas')->label(__('ui.comun.personas'))->numeric()->minValue(1)->maxValue(100)
                        ->default(fn (): int => max(1, request()->integer('personas', 2)))
                        ->required($esReserva)->visible($esReserva),
                    Select::make('unidad_id')->label(__('ui.comun.unidad'))->required()->native(false)->searchable()->live()
                        ->helperText(__('ui.reservas.unidad_ayuda'))
                        ->options(fn (Get $get): array => self::opcionesUnidad($get('propiedad_id')))
                        ->disableOptionWhen(fn (string $value, Get $get, ?Reserva $record): bool => in_array((int) $value, self::ocupadas($get, $record), true))
                        ->default(fn (): ?int => self::unidadDeLaDireccion()?->id)
                        ->validationMessages(['in' => __('ui.reservas.error_ocupada')])
                        ->afterStateUpdated($alCambiar)
                        ->columnSpan(['lg' => 2]),
                    TextInput::make('origen_texto')->maxLength(100)
                        ->label(fn (Get $get): string => $esReserva($get) ? __('ui.comun.origen') : __('ui.reservas.motivo'))
                        ->datalist(fn (Get $get): array => $esReserva($get) ? ['Directo', 'Booking', 'Airbnb', 'Expedia', 'Teléfono'] : []),
                ]),
            Section::make(__('ui.comun.huesped'))->columnSpanFull()
                ->visible($esReserva)
                ->schema([
                    Select::make('titular_id')->label(__('ui.comun.huesped'))->required()->native(false)->searchable()
                        ->helperText(__('ui.reservas.huesped_ayuda'))
                        ->getSearchResultsUsing(fn (string $search, Get $get): array => self::buscarHuespedes((int) $get('propiedad_id'), $search))
                        ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => Huesped::query()->where('propiedad_id', (int) $get('propiedad_id'))->find((int) $value)?->nombreCompleto())
                        ->disabled(fn (Get $get): bool => blank($get('propiedad_id')))
                        ->createOptionModalHeading(__('ui.huespedes.nuevo'))
                        ->createOptionForm([
                            TextInput::make('nombre')->label(__('ui.huespedes.nombre'))->required()->maxLength(100),
                            TextInput::make('apellido1')->label(__('ui.huespedes.apellido1'))->maxLength(100),
                            TextInput::make('apellido2')->label(__('ui.huespedes.apellido2'))->maxLength(100),
                            TextInput::make('telefono')->label(__('ui.huespedes.telefono'))->tel()->maxLength(50),
                            TextInput::make('correo')->label(__('ui.huespedes.correo'))->email()->maxLength(150),
                        ])
                        ->createOptionUsing(fn (array $data, Get $get): int => self::crearHuesped((int) $get('propiedad_id'), $data)),
                ]),
            Section::make(__('ui.reservas.dinero'))->columnSpanFull()
                ->visible($esReserva)
                ->columns(['default' => 1, 'md' => 2, 'lg' => 4])
                ->schema([
                    CampoDinero::make('total')->label(__('ui.comun.total'))->required()->default(0)
                        ->helperText(fn (Get $get, ?Reserva $record): string => self::textoTarifa($get, $record))
                        ->suffixAction(
                            Action::make('aplicarTarifa')->label(__('ui.reservas.aplicar_tarifa'))->tooltip(__('ui.reservas.aplicar_tarifa'))
                                ->icon(Heroicon::OutlinedCalculator)
                                ->action(fn (Get $get, Set $set) => self::aplicarTarifa($get, $set))
                        )
                        ->columnSpan(['lg' => 2]),
                    CampoDinero::make('descuento')->label(__('ui.reservas.descuento'))->default(0)->live(onBlur: true)
                        ->afterStateUpdated($alCambiar),
                    CampoDinero::make('deposito')->label(__('ui.reservas.deposito'))->default(0),
                    CampoDinero::make('comision')->label(__('ui.reservas.comision'))->default(0),
                ]),
            Section::make(__('ui.reservas.comentario'))->columnSpanFull()
                ->collapsible()
                ->schema([
                    Textarea::make('comentario')->hiddenLabel()->rows(3)->maxLength(5000),
                ]),
        ]);
    }

    /**
     * Pone en «Total» el precio de la tarifa menos el descuento, si la unidad tiene tarifa.
     */
    public static function aplicarTarifa(Get $get, Set $set): void
    {
        $cotizacion = self::cotizacion($get);
        if ($cotizacion === null) {
            return;
        }
        $moneda = CampoDinero::moneda($get('propiedad_id'));
        $descuento = Dinero::aUnidadMenor(is_numeric($get('descuento')) ? (string) $get('descuento') : null, $moneda);
        $set('total', Dinero::aDecimal(max(0, $cotizacion->total - $descuento), $moneda));
    }

    /**
     * Total inicial cuando el calendario o la disponibilidad ya traen la unidad y las fechas.
     */
    public static function totalDeLaDireccion(): ?string
    {
        $unidad = self::unidadDeLaDireccion();
        $llegada = self::fechaDeLaDireccion('llegada');
        $salida = self::fechaDeLaDireccion('salida');
        if ($unidad === null || $llegada === null || $salida === null) {
            return null;
        }
        $cotizacion = Cotizador::cotizar($unidad, CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida));

        return $cotizacion === null ? null : Dinero::aDecimal($cotizacion->total, $unidad->propiedad->moneda);
    }

    private static function tipo(Get $get): TipoReserva
    {
        $tipo = $get('tipo');

        return $tipo instanceof TipoReserva ? $tipo : (TipoReserva::tryFrom((string) $tipo) ?? TipoReserva::Reserva);
    }

    private static function propiedadInicial(): ?int
    {
        $permitidas = ReservaResource::propiedadesGestionables();
        $unidad = self::unidadDeLaDireccion();
        if ($unidad !== null) {
            return $unidad->propiedad_id;
        }

        return count($permitidas) === 1 ? $permitidas[0] : null;
    }

    private static function unidadDeLaDireccion(): ?Unidad
    {
        $id = request()->integer('unidad');
        if ($id <= 0) {
            return null;
        }

        return Unidad::query()->with('propiedad')->where('activa', true)->whereIn('propiedad_id', ReservaResource::propiedadesGestionables())->find($id);
    }

    private static function fechaDeLaDireccion(string $parametro): ?string
    {
        $valor = (string) request()->query($parametro, '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return null;
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $valor)?->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private static function opcionesUnidad(mixed $propiedadId): array
    {
        if (blank($propiedadId)) {
            return [];
        }

        return Unidad::query()->with('tarifa')->where('propiedad_id', (int) $propiedadId)->where('activa', true)
            ->orderBy('orden')->orderBy('nombre')->get()
            ->mapWithKeys(fn (Unidad $u): array => [$u->id => trim($u->nombre.($u->capacidad > 0 ? ' · '.__('ui.disponibilidad.capacidad', ['personas' => $u->capacidad]) : '').($u->tarifa ? ' · '.$u->tarifa->nombre : ''))])
            ->all();
    }

    /**
     * @return list<int>
     */
    private static function ocupadas(Get $get, ?Reserva $record): array
    {
        [$llegada, $salida] = self::fechas($get);
        if (blank($get('propiedad_id')) || $llegada === null || $salida === null) {
            return [];
        }

        return Disponibilidad::ocupadas((int) $get('propiedad_id'), $llegada->format('Y-m-d'), $salida->format('Y-m-d'), $record?->id);
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function fechas(Get $get): array
    {
        try {
            $llegada = blank($get('llegada')) ? null : CarbonImmutable::parse((string) $get('llegada'))->startOfDay();
            $salida = blank($get('salida')) ? null : CarbonImmutable::parse((string) $get('salida'))->startOfDay();
        } catch (Throwable) {
            return [null, null];
        }

        return $llegada !== null && $salida !== null && $salida->gt($llegada) ? [$llegada, $salida] : [null, null];
    }

    private static function cotizacion(Get $get): ?Cotizacion
    {
        [$llegada, $salida] = self::fechas($get);
        if ($llegada === null || $salida === null || blank($get('unidad_id'))) {
            return null;
        }
        $unidad = Unidad::query()->where('propiedad_id', (int) $get('propiedad_id'))->find((int) $get('unidad_id'));

        return $unidad === null ? null : Cotizador::cotizar($unidad, $llegada, $salida);
    }

    private static function textoTarifa(Get $get, ?Reserva $record): string
    {
        $cotizacion = self::cotizacion($get);
        if ($cotizacion === null) {
            return __('ui.reservas.sin_tarifa');
        }

        return __('ui.reservas.precio_tarifa', [
            'tarifa' => $cotizacion->tarifa,
            'precio' => Formato::dinero($cotizacion->total, $record->moneda ?? CampoDinero::moneda($get('propiedad_id'))),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function buscarHuespedes(int $propiedadId, string $busqueda): array
    {
        $texto = '%'.trim($busqueda).'%';

        return Huesped::query()->where('propiedad_id', $propiedadId)
            ->where(fn (Builder $q): Builder => $q->where('nombre', 'like', $texto)->orWhere('apellido1', 'like', $texto)->orWhere('apellido2', 'like', $texto)
                ->orWhereHas('contactos', fn (Builder $c): Builder => $c->where('valor_normalizado', 'like', mb_strtolower($texto))))
            ->orderBy('apellido1')->orderBy('nombre')->limit(30)->get()
            ->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function crearHuesped(int $propiedadId, array $data): int
    {
        abort_unless(in_array($propiedadId, ReservaResource::propiedadesGestionables(), true), 403);

        $huesped = Huesped::query()->create([
            'propiedad_id' => $propiedadId,
            'nombre' => $data['nombre'] ?? null,
            'apellido1' => $data['apellido1'] ?? null,
            'apellido2' => $data['apellido2'] ?? null,
        ]);
        $orden = 0;
        foreach ([TipoContacto::Telefono->value => $data['telefono'] ?? null, TipoContacto::Correo->value => $data['correo'] ?? null] as $tipo => $valor) {
            if (filled($valor)) {
                $huesped->contactos()->create([
                    'tipo' => $tipo, 'valor' => (string) $valor, 'valor_normalizado' => Contactos::normalizar($tipo, (string) $valor), 'orden' => $orden++,
                ]);
            }
        }

        return $huesped->id;
    }
}
