<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Schemas;

use App\Camping\AtributosLugar;
use App\Camping\CupoExtras;
use App\Enums\BaseCargoCanal;
use App\Enums\EntidadCampo;
use App\Enums\EstadoReserva;
use App\Enums\TipoContacto;
use App\Enums\TipoEquipo;
use App\Enums\TipoReserva;
use App\Filament\Campos\CampoDinero;
use App\Filament\Campos\CamposPropiosPanel;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Canal;
use App\Models\ConceptoCargo;
use App\Models\Empresa;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\Cotizador;
use App\Reservas\Cupones;
use App\Reservas\Presupuesto;
use App\Reservas\ReglasCargo;
use App\Reservas\Restricciones;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Servicios\Disponibilidad;
use App\Support\Contactos;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

/**
 * Alta y cambio de reservas y bloqueos de una propiedad independiente. Las reglas de verdad están
 * en GestorReservas; aquí se guía a quien escribe: unidades ocupadas desactivadas, y el total ya
 * calculado con la tarifa, los cargos y los impuestos. El calendario y la disponibilidad traen
 * datos por la dirección.
 */
class ReservaForm
{
    /** Hasta aquí se ve la lista sin buscador: en el teléfono, buscar abre el teclado y tapa la lista. */
    private const UNIDADES_SIN_BUSCADOR = 10;

    /** Filament solo abre la sección del primer error: una plegada con otro lo dejaría oculto y parecería que el botón no hace nada. */
    private const ABRIR_CON_ERROR = [
        'x-on:form-validation-error.window' => '$nextTick(() => { if ($el.querySelector(\'[data-validation-error]\')) $el.querySelector(\'.fi-section\')?.dispatchEvent(new CustomEvent(\'expand\')) })',
    ];

    public static function configure(Schema $schema): Schema
    {
        $esReserva = fn (Get $get): bool => self::tipo($get) === TipoReserva::Reserva;
        // Al crear, el total sigue a los datos; al editar solo cambia con el botón, para no pisar
        // un precio ya acordado.
        $alCambiar = function (Get $get, Set $set, string $operation): void {
            if ($operation === 'create') {
                self::aplicarTarifa($get, $set);
            }
        };

        return $schema->components([
            Hidden::make('version'),
            // Primero lo que decide todo lo demás: cuándo, cuántos y dónde. Lo que casi nunca cambia va plegado abajo.
            Section::make(__('ui.reservas.estancia'))->columnSpanFull()
                ->columns(['default' => 2, 'lg' => 4])
                ->schema([
                    // Con una sola propiedad no hay nada que preguntar, y el valor sigue viajando.
                    Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)->live()
                        ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all())
                        ->default(fn (): ?int => self::propiedadInicial())
                        ->disabledOn('edit')
                        ->hidden(fn (): bool => count(ReservaResource::propiedadesGestionables()) === 1)
                        ->dehydratedWhenHidden()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('unidad_id', null);
                            $set('titular_id', null);
                            $set('total', null);
                            $set('cargos', []);
                        })
                        ->columnSpanFull(),
                    DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required()->live(onBlur: true)
                        ->extraInputAttributes(RangoFechas::llegada())
                        ->default(fn (): ?string => self::fechaDeLaDireccion('llegada'))
                        ->afterStateUpdated(function (Get $get, Set $set, string $operation) use ($alCambiar): void {
                            RangoFechas::moverFin($get, $set, 'llegada', 'salida');
                            $alCambiar($get, $set, $operation);
                        }),
                    DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->live(onBlur: true)->after('llegada')
                        ->extraInputAttributes(RangoFechas::fin('estancia'))
                        ->default(fn (): ?string => self::fechaDeLaDireccion('salida'))
                        ->afterStateUpdated($alCambiar),
                    TextInput::make('personas')->label(__('ui.comun.personas'))->numeric()->minValue(1)->maxValue(100)->live(onBlur: true)
                        ->default(fn (): int => max(1, request()->integer('personas', 2)))
                        ->afterStateUpdated($alCambiar)
                        ->required($esReserva)->visible($esReserva),
                    // Solo si algún impuesto lo necesita: en la mayoría de los alojamientos sería una pregunta de más.
                    TextInput::make('ninos')->label(__('caja.impuestos.menores'))->helperText(__('caja.impuestos.menores_ayuda'))->numeric()->minValue(0)->maxValue(100)->live(onBlur: true)
                        ->afterStateUpdated($alCambiar)
                        ->visible(fn (Get $get): bool => $esReserva($get) && self::hayEdadMinima($get('propiedad_id'))),
                    // Los avisos de la estancia van aquí: dependen de la unidad, y en media pantalla, bajo la salida, no se leerían.
                    Select::make('unidad_id')->label(__('ui.comun.unidad'))->required()->native(false)->live()
                        // Con pocas se ve la lista; un buscador solo abriría el teclado encima de ella.
                        ->searchable(fn (Get $get): bool => count(self::opcionesUnidad($get('propiedad_id'))) > self::UNIDADES_SIN_BUSCADOR)
                        ->helperText(fn (Get $get, ?Reserva $record): string => trim(self::textoUnidades($get, $record).' '.self::avisoEstancia($get)))
                        ->options(fn (Get $get): array => self::opcionesUnidad($get('propiedad_id')))
                        ->disableOptionWhen(fn (string $value, Get $get, ?Reserva $record): bool => in_array((int) $value, self::ocupadas($get, $record), true))
                        ->default(fn (): ?int => self::unidadDeLaDireccion()?->id)
                        ->validationMessages(['in' => __('ui.reservas.error_ocupada')])
                        ->afterStateUpdated(function (Get $get, Set $set, string $operation, ?Reserva $record) use ($alCambiar): void {
                            // La que esta unidad no vende no se queda elegida.
                            if (! array_key_exists((int) $get('tarifa_id'), self::tarifasDeUnidad($get('propiedad_id'), $get('unidad_id'), $record?->tarifa_id))) {
                                $set('tarifa_id', null);
                            }
                            $alCambiar($get, $set, $operation);
                        })
                        ->hintAction(
                            Action::make('primeraLibre')->label(__('ui.reservas.primera_libre'))->icon(Heroicon::OutlinedSparkles)
                                ->action(function (Get $get, Set $set, string $operation): void {
                                    [$llegada, $salida] = self::fechas($get);
                                    if ($llegada === null || $salida === null || blank($get('propiedad_id'))) {
                                        Notification::make()->warning()->title(__('ui.reservas.primera_libre_sin_fechas'))->send();

                                        return;
                                    }
                                    $unidad = Disponibilidad::primeraLibre(self::propiedadPermitida($get('propiedad_id')), $llegada->format('Y-m-d'), $salida->format('Y-m-d'), max(1, (int) $get('personas')));
                                    if ($unidad === null) {
                                        Notification::make()->warning()->title(__('ui.reservas.primera_libre_ninguna'))->send();
                                    }
                                    $set('unidad_id', $unidad?->id);
                                    $set('tarifa_id', null);
                                    if ($operation === 'create') {
                                        self::aplicarTarifa($get, $set);
                                    }
                                })
                        )
                        ->columnSpanFull(),
                    // Solo si esa unidad se vende con más de una.
                    Select::make('tarifa_id')->label(__('ui.reservas.tarifa'))->native(false)->live()->placeholder(__('ui.reservas.tarifa_principal'))
                        ->options(fn (Get $get, ?Reserva $record): array => self::tarifasDeUnidad($get('propiedad_id'), $get('unidad_id'), $record?->tarifa_id))
                        ->visible(fn (Get $get, ?Reserva $record): bool => $esReserva($get) && count(self::tarifasDeUnidad($get('propiedad_id'), $get('unidad_id'), $record?->tarifa_id)) > 1)
                        ->afterStateUpdated($alCambiar)
                        ->columnSpanFull(),
                    ToggleButtons::make('tipo')->label(__('ui.reservas.tipo'))->options(TipoReserva::opciones())->required()->inline()->grouped()->live()
                        ->default(TipoReserva::Reserva->value)
                        ->disabledOn('edit')
                        ->columnSpanFull(),
                    TextInput::make('origen_texto')->maxLength(100)->live(onBlur: true)
                        ->label(fn (Get $get): string => $esReserva($get) ? __('ui.comun.origen') : __('ui.reservas.motivo'))
                        ->datalist(fn (Get $get): array => $esReserva($get) ? self::canales($get('propiedad_id')) : [])
                        ->afterStateUpdated($alCambiar)
                        ->columnSpanFull(),
                ]),
            Section::make(__('ui.comun.huesped'))->columnSpanFull()
                ->visible($esReserva)
                ->schema([
                    Select::make('titular_id')->label(__('ui.comun.huesped'))->required()->native(false)->searchable()
                        ->helperText(__('ui.reservas.huesped_ayuda'))
                        ->getSearchResultsUsing(fn (string $search, Get $get): array => self::buscarHuespedes(self::propiedadPermitida($get('propiedad_id')), $search))
                        ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => Huesped::query()->where('propiedad_id', self::propiedadPermitida($get('propiedad_id')))->find((int) $value)?->nombreCompleto())
                        ->disabled(fn (Get $get): bool => blank($get('propiedad_id')))
                        ->createOptionModalHeading(__('ui.huespedes.nuevo'))
                        ->createOptionForm([
                            TextInput::make('nombre')->label(__('ui.huespedes.nombre'))->required()->maxLength(100),
                            TextInput::make('apellido1')->label(__('ui.huespedes.apellido1'))->maxLength(100),
                            TextInput::make('apellido2')->label(__('ui.huespedes.apellido2'))->maxLength(100),
                            TextInput::make('telefono')->label(__('ui.huespedes.telefono'))->tel()->maxLength(50),
                            TextInput::make('correo')->label(__('ui.huespedes.correo'))->email()->maxLength(150),
                        ])
                        ->createOptionUsing(fn (array $data, Get $get): int => self::crearHuesped(self::propiedadPermitida($get('propiedad_id')), $data)),
                ]),
            CamposPropiosPanel::seccion(EntidadCampo::Reserva, $esReserva),
            Section::make(__('ui.cargos.modelos'))->columnSpanFull()
                ->visible(fn (Get $get): bool => $esReserva($get) && self::hayConceptosOpcionales($get('propiedad_id')))
                ->collapsed(fn (Get $get): bool => blank($get('cargos')))
                ->extraAlpineAttributes(self::ABRIR_CON_ERROR)
                ->schema([
                    Repeater::make('cargos')->label(__('ui.cargos.modelos'))->hiddenLabel()->defaultItems(0)
                        ->addActionLabel(__('ui.reservas.anadir_cargo'))
                        ->columns(['default' => 1, 'md' => 2])
                        ->live()
                        ->afterStateUpdated($alCambiar)
                        ->schema([
                            Select::make('concepto_cargo_id')->label(__('ui.cargos.modelo_campo'))->required()->native(false)->live()
                                ->options(fn (Get $get): array => self::opcionesCargo($get('../../propiedad_id')))
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->afterStateUpdated(self::recalcularDesdeLinea()),
                            TextInput::make('cantidad')->label(__('ui.reservas.cantidad'))->numeric()->minValue(0.01)->default(1)->required()->live(onBlur: true)
                                ->afterStateUpdated(self::recalcularDesdeLinea()),
                        ]),
                ]),
            Section::make(__('camping.equipo.titulo'))->columnSpanFull()
                ->collapsed(fn (Get $get): bool => array_filter((array) $get('equipo'), filled(...)) === [])
                ->extraAlpineAttributes(self::ABRIR_CON_ERROR)
                ->visible(fn (Get $get): bool => $esReserva($get) && Modulos::activo(Modulo::Camping))
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('equipo.tipo')->label(__('camping.equipo.tipo'))->native(false)->options(TipoEquipo::opciones()),
                    TextInput::make('equipo.largo')->label(fn (Get $get): string => __('camping.equipo.largo_panel', ['unidad' => self::unidadLargo($get('propiedad_id'))]))->numeric()->minValue(0)->maxValue(1000)->step('any')->live(onBlur: true),
                    TextInput::make('equipo.vehiculos')->label(__('camping.equipo.vehiculos'))->numeric()->integer()->minValue(0)->maxValue(20),
                    TextInput::make('equipo.placas')->label(__('camping.equipo.placas'))->maxLength(100),
                ]),
            Section::make(__('propietarios.reserva.titulo'))->description(__('propietarios.reserva.ayuda'))->columnSpanFull()
                ->visible(fn (Get $get): bool => ! $esReserva($get) && Modulos::activo(Modulo::Propietarios))
                ->schema([
                    Select::make('propietario_id')->label(__('propietarios.modelo'))->native(false)
                        ->options(fn (Get $get): array => Propietario::query()->where('propiedad_id', self::propiedadPermitida($get('propiedad_id')))
                            ->whereIn('id', Unidad::query()->whereKey((int) $get('unidad_id'))->select('propietario_id'))->pluck('nombre', 'id')->all()),
                ]),
            Hidden::make('sin_catalogo')->dehydrated(false),
            Hidden::make('esta_reserva')->dehydrated(false),
            Section::make(__('ui.reservas.dinero'))->columnSpanFull()
                ->visible($esReserva)
                ->schema([
                    CampoDinero::make('total')->label(__('ui.comun.total'))->required()->default(0)
                        ->helperText(fn (Get $get, ?Reserva $record): string => self::textoPresupuesto($get, $record))
                        ->suffixAction(
                            Action::make('aplicarTarifa')->label(__('ui.reservas.aplicar_tarifa'))->tooltip(__('ui.reservas.aplicar_tarifa'))
                                ->icon(Heroicon::OutlinedCalculator)
                                ->action(fn (Get $get, Set $set) => self::aplicarTarifa($get, $set))
                        ),
                    Section::make(__('ui.reservas.descuento_y_mas'))->compact()
                        ->collapsed(fn (Get $get): bool => blank($get('cupon_codigo')) && (float) $get('descuento') === 0.0 && (float) $get('deposito') === 0.0 && (float) $get('comision') === 0.0)
                        ->extraAlpineAttributes(self::ABRIR_CON_ERROR)
                        ->columns(['default' => 2, 'lg' => 4])
                        ->schema([
                            TextInput::make('cupon_codigo')->label(__('ui.cupones.modelo_campo'))->maxLength(40)->live(onBlur: true)
                                ->visible(fn (): bool => Modulos::activo(Modulo::Cupones))
                                ->rules([
                                    fn (Get $get, ?Reserva $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                        $motivo = self::motivoCupon($get, (string) $value, $record);
                                        if ($motivo !== null) {
                                            $fail($motivo);
                                        }
                                    },
                                ])
                                ->afterStateUpdated(function (Get $get, Set $set, string $operation): void {
                                    self::aplicarCupon($get, $set);
                                    if ($operation === 'create') {
                                        self::aplicarTarifa($get, $set);
                                    }
                                }),
                            CampoDinero::make('descuento')->label(__('ui.reservas.descuento'))->default(0)->live(onBlur: true)
                                ->afterStateUpdated($alCambiar),
                            CampoDinero::make('deposito')->label(__('ui.reservas.deposito'))->default(0),
                            CampoDinero::make('comision')->label(__('ui.reservas.comision'))->default(0),
                        ]),
                ]),
            Section::make(fn (Get $get): string => $esReserva($get) ? __('ui.reservas.mas_detalles') : __('ui.reservas.comentario'))->columnSpanFull()
                ->collapsed(fn (Get $get): bool => blank($get('comentario')) && blank($get('empresa_id')) && in_array($get('estado'), [null, EstadoReserva::Confirmada, EstadoReserva::Confirmada->value], true))
                ->extraAlpineAttributes(self::ABRIR_CON_ERROR)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    Select::make('estado')->label(__('ui.comun.estado'))->required()->native(false)
                        ->options([
                            EstadoReserva::Confirmada->value => EstadoReserva::Confirmada->etiqueta(),
                            EstadoReserva::SinConfirmar->value => EstadoReserva::SinConfirmar->etiqueta(),
                        ])
                        ->default(EstadoReserva::Confirmada->value)
                        ->visible($esReserva),
                    Select::make('empresa_id')->label(__('caja.empresas.campo'))->helperText(__('caja.empresas.campo_ayuda'))->native(false)->searchable()->live()
                        ->options(fn (Get $get, ?Reserva $record): array => self::empresas($get('propiedad_id'), $record?->empresa_id))
                        ->afterStateUpdated($alCambiar)
                        ->visible(fn (Get $get, ?Reserva $record): bool => $esReserva($get) && Modulos::activo(Modulo::Empresas) && self::empresas($get('propiedad_id'), $record?->empresa_id) !== []),
                    Textarea::make('comentario')->label(__('ui.reservas.comentario'))->rows(3)->maxLength(5000)->columnSpanFull(),
                ]),
        ]);
    }

    /**
     * Pone en «Total» lo que sale de la tarifa, los cargos y los impuestos que se suman, menos el
     * descuento; y en «Comisión», lo del canal. Si la unidad no tiene tarifa, no toca el total.
     */
    public static function aplicarTarifa(Get $get, Set $set, string $ruta = ''): void
    {
        $presupuesto = self::presupuesto($get, $ruta);
        if ($presupuesto === null || $presupuesto->cotizacion === null) {
            return;
        }
        $moneda = CampoDinero::moneda($get($ruta.'propiedad_id'));
        // Lo que no viene del catálogo, como lo que marcó un medidor, sigue en la reserva aunque cambien las fechas.
        $set($ruta.'total', Dinero::aDecimal($presupuesto->total() + (int) $get($ruta.'sin_catalogo'), $moneda));

        $canal = Canal::query()->where('propiedad_id', self::propiedadPermitida($get($ruta.'propiedad_id')))->where('nombre', (string) $get($ruta.'origen_texto'))->first();
        if ($canal !== null && $canal->comision_porcentaje !== null) {
            $base = match ($canal->comision_base) {
                BaseCargoCanal::TarifaBase => $presupuesto->alojamiento,
                BaseCargoCanal::SinImpuestos => $presupuesto->total() - $presupuesto->impuestosAdicionales() - $presupuesto->impuestosIncluidos(),
                default => $presupuesto->total(),
            };
            $set($ruta.'comision', Dinero::aDecimal((int) round($base * (float) $canal->comision_porcentaje / 100), $moneda));
        }
        $empresa = blank($get($ruta.'empresa_id')) ? null : Empresa::query()->where('propiedad_id', self::propiedadPermitida($get($ruta.'propiedad_id')))->find((int) $get($ruta.'empresa_id'));
        if ($empresa?->comision_porcentaje !== null) {
            $set($ruta.'comision', Dinero::aDecimal((int) round($presupuesto->total() * (float) $empresa->comision_porcentaje / 100), $moneda));
        }
    }

    /**
     * Las activas y la que ya tiene la reserva, aunque ya no lo esté: cambiar otra cosa no debe quitársela.
     *
     * @return array<int, string>
     */
    private static function empresas(mixed $propiedadId, ?int $actual = null): array
    {
        return blank($propiedadId) ? [] : Empresa::query()->where('propiedad_id', self::propiedadPermitida($propiedadId))
            ->where(fn (Builder $q): Builder => $q->where('activa', true)->orWhere('id', $actual))->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    private static function hayEdadMinima(mixed $propiedadId): bool
    {
        return ! blank($propiedadId) && Impuesto::query()->where('propiedad_id', self::propiedadPermitida($propiedadId))->where('activo', true)->whereNotNull('edad_minima')->exists();
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
        $presupuesto = Cotizador::presupuesto($unidad, CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida), max(1, request()->integer('personas', 2)));

        return $presupuesto->cotizacion === null ? null : Dinero::aDecimal($presupuesto->total(), $unidad->propiedad->moneda);
    }

    private static function recalcularDesdeLinea(): Closure
    {
        return function (Get $get, Set $set, string $operation): void {
            if ($operation === 'create') {
                self::aplicarTarifa($get, $set, '../../');
            }
        };
    }

    private static function aplicarCupon(Get $get, Set $set): void
    {
        $presupuesto = self::presupuesto($get, '', sinDescuento: true);
        $cupon = Cupones::buscar(self::propiedadPermitida($get('propiedad_id')), (string) $get('cupon_codigo'));
        if ($presupuesto === null || $cupon === null) {
            return;
        }
        $set('descuento', Dinero::aDecimal(Cupones::descuento($cupon, $presupuesto->alojamiento), CampoDinero::moneda($get('propiedad_id'))));
    }

    private static function motivoCupon(Get $get, string $codigo, ?Reserva $record): ?string
    {
        if (trim($codigo) === '') {
            return null;
        }
        $cupon = Cupones::buscar(self::propiedadPermitida($get('propiedad_id')), $codigo);
        if ($cupon === null) {
            return __('ui.cupones.error_no_existe');
        }
        [$llegada, $salida] = self::fechas($get);
        if ($llegada === null || $salida === null) {
            return null;
        }

        return Cupones::motivoRechazo($cupon, $llegada, (int) $llegada->diffInDays($salida), $record?->id);
    }

    private static function tipo(Get $get): TipoReserva
    {
        $tipo = $get('tipo');

        return $tipo instanceof TipoReserva ? $tipo : (TipoReserva::tryFrom((string) $tipo) ?? TipoReserva::Reserva);
    }

    private static function propiedadPermitida(mixed $valor): int
    {
        return Acceso::propiedadElegida($valor, ReservaResource::propiedadesPermitidas());
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

        return Unidad::query()->with('tarifa')->where('propiedad_id', self::propiedadPermitida($propiedadId))->where('activa', true)
            ->orderBy('orden')->orderBy('nombre')->get()
            ->mapWithKeys(fn (Unidad $u): array => [$u->id => trim($u->nombre.($u->capacidad > 0 ? ' · '.__('ui.disponibilidad.capacidad', ['personas' => $u->capacidad.($u->camas_extra > 0 ? ' + '.$u->camas_extra : '')]) : '').($u->tarifa ? ' · '.$u->tarifa->nombre : ''))])
            ->all();
    }

    /**
     * Cuántas quedan para esas fechas, en cuanto se saben: así se ve antes de abrir la lista.
     */
    private static function textoUnidades(Get $get, ?Reserva $record): string
    {
        [$llegada, $salida] = self::fechas($get);
        if ($llegada === null || $salida === null) {
            return __('ui.reservas.unidad_ayuda');
        }
        $unidades = array_keys(self::opcionesUnidad($get('propiedad_id')));

        return trans_choice('ui.reservas.unidades_libres', count(array_diff($unidades, self::ocupadas($get, $record))), ['total' => count($unidades)]);
    }

    /**
     * Las que vende esa unidad, y la que ya tiene la reserva aunque la hayan retirado.
     *
     * @return array<int, string>
     */
    private static function tarifasDeUnidad(mixed $propiedadId, mixed $unidadId, ?int $actual = null): array
    {
        $unidad = blank($propiedadId) || blank($unidadId) ? null : Unidad::query()->where('propiedad_id', self::propiedadPermitida($propiedadId))->find((int) $unidadId);
        if ($unidad === null) {
            return [];
        }

        return Tarifa::query()->where('propiedad_id', $unidad->propiedad_id)
            ->where(fn (Builder $q): Builder => $q->where(fn (Builder $vende): Builder => $vende->where('activa', true)->whereIn('id', $unidad->tarifasIds()))->orWhere('id', $actual))
            ->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    private static function opcionesCargo(mixed $propiedadId): array
    {
        if (blank($propiedadId)) {
            return [];
        }

        return ConceptoCargo::query()->where('propiedad_id', self::propiedadPermitida($propiedadId))->where('activo', true)->where('obligatorio', false)
            ->orderBy('orden')->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    private static function hayConceptosOpcionales(mixed $propiedadId): bool
    {
        return self::opcionesCargo($propiedadId) !== [];
    }

    /**
     * @return list<string>
     */
    private static function canales(mixed $propiedadId): array
    {
        $canales = blank($propiedadId) ? [] : Canal::query()->where('propiedad_id', self::propiedadPermitida($propiedadId))->where('activo', true)->orderBy('nombre')->pluck('nombre')->all();

        return $canales !== [] ? array_values($canales) : ['Directo', 'Booking', 'Airbnb', 'Expedia', 'Teléfono'];
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

        return Disponibilidad::ocupadas(self::propiedadPermitida($get('propiedad_id')), $llegada->format('Y-m-d'), $salida->format('Y-m-d'), $record?->id);
    }

    /**
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private static function fechas(Get $get, string $ruta = ''): array
    {
        try {
            $llegada = blank($get($ruta.'llegada')) ? null : CarbonImmutable::parse((string) $get($ruta.'llegada'))->startOfDay();
            $salida = blank($get($ruta.'salida')) ? null : CarbonImmutable::parse((string) $get($ruta.'salida'))->startOfDay();
        } catch (Throwable) {
            return [null, null];
        }

        return $llegada !== null && $salida !== null && $salida->gt($llegada) ? [$llegada, $salida] : [null, null];
    }

    private static function presupuesto(Get $get, string $ruta = '', bool $sinDescuento = false): ?Presupuesto
    {
        [$llegada, $salida] = self::fechas($get, $ruta);
        if ($llegada === null || $salida === null || blank($get($ruta.'unidad_id'))) {
            return null;
        }
        $unidad = Unidad::query()->where('propiedad_id', self::propiedadPermitida($get($ruta.'propiedad_id')))->find((int) $get($ruta.'unidad_id'));
        if ($unidad === null) {
            return null;
        }
        $elegidos = [];
        foreach ((array) $get($ruta.'cargos') as $linea) {
            $concepto = (int) ($linea['concepto_cargo_id'] ?? 0);
            if ($concepto > 0) {
                $elegidos[$concepto] = max(0.01, (float) ($linea['cantidad'] ?? 1));
            }
        }
        $moneda = CampoDinero::moneda($get($ruta.'propiedad_id'));
        $descuento = $sinDescuento ? 0 : Dinero::aUnidadMenor(is_numeric($get($ruta.'descuento')) ? (string) $get($ruta.'descuento') : null, $moneda);

        $canalCobra = (bool) Canal::query()->where('propiedad_id', $unidad->propiedad_id)->where('nombre', (string) $get($ruta.'origen_texto'))->value('cobra_impuestos');

        return Cotizador::presupuesto($unidad, $llegada, $salida, max(1, (int) $get($ruta.'personas')), $elegidos, null, $descuento, max(0, (int) $get($ruta.'ninos')), $canalCobra, blank($get($ruta.'tarifa_id')) ? null : (int) $get($ruta.'tarifa_id'));
    }

    /**
     * Si el equipo no cabe en el lugar, o si un extra ya no alcanza para esas noches (D-110, D-113).
     *
     * @return list<string>
     */
    private static function avisosCamping(Get $get, Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida): array
    {
        if (! Modulos::activo(Modulo::Camping)) {
            return [];
        }
        $avisos = [];
        $largo = $get('equipo.largo');
        $maximo = $unidad->atributo('largo_maximo');
        if (is_numeric($largo) && is_numeric($maximo) && (float) $largo > (float) $maximo) {
            $avisos[] = __('camping.equipo.no_cabe', ['lugar' => $unidad->nombre, 'maximo' => (float) $maximo, 'unidad' => AtributosLugar::unidadLargo($unidad->propiedad)]);
        }
        $esta = blank($get('esta_reserva')) ? null : (int) $get('esta_reserva');
        foreach ((array) $get('cargos') as $linea) {
            $concepto = ConceptoCargo::query()->where('propiedad_id', $unidad->propiedad_id)->whereNotNull('cupo_diario')->find((int) ($linea['concepto_cargo_id'] ?? 0));
            if ($concepto !== null && (float) ($linea['cantidad'] ?? 1) > (CupoExtras::libres($concepto, $llegada, $salida, $esta) ?? 0)) {
                $avisos[] = __('camping.extras.agotado', ['extra' => $concepto->nombre]);
            }
        }

        return $avisos;
    }

    private static function unidadLargo(mixed $propiedadId): string
    {
        $propiedad = blank($propiedadId) ? null : Propiedad::query()->find((int) $propiedadId);

        return $propiedad === null ? 'm' : AtributosLugar::unidadLargo($propiedad);
    }

    private static function avisoEstancia(Get $get): ?string
    {
        $avisos = [];
        $presupuesto = self::presupuesto($get);
        if ($presupuesto?->cotizacion !== null && ! $presupuesto->cotizacion->cumpleEstanciaMinima()) {
            $avisos[] = __('ui.reservas.aviso_estancia_minima', ['noches' => $presupuesto->cotizacion->estanciaMinima]);
        }
        $tarifa = $presupuesto?->cotizacion?->tarifaId === null ? null : Tarifa::query()->find($presupuesto->cotizacion->tarifaId);
        if ($tarifa !== null && ! $tarifa->admitePersonas(max(1, (int) $get('personas')))) {
            $avisos[] = max(1, (int) $get('personas')) < (int) $tarifa->personas_minimas
                ? __('ui.reservas.aviso_tarifa_minimo', ['tarifa' => $tarifa->nombre, 'personas' => $tarifa->personas_minimas])
                : __('ui.reservas.aviso_tarifa_maximo', ['tarifa' => $tarifa->nombre, 'personas' => $tarifa->personas_maximas]);
        }
        [$llegada, $salida] = self::fechas($get);
        $unidad = $llegada === null || $salida === null || blank($get('unidad_id')) ? null
            : Unidad::query()->with('propiedad')->where('propiedad_id', self::propiedadPermitida($get('propiedad_id')))->find((int) $get('unidad_id'));
        if ($unidad !== null) {
            // Solo un aviso: un caso especial lo decide quien atiende, no el sistema.
            $avisos = [...$avisos, ...Restricciones::deUnidad($unidad, $llegada, $salida, $unidad->propiedad->hoy()), ...self::avisosCamping($get, $unidad, $llegada, $salida)];
            $camas = ReglasCargo::camasExtra($unidad, max(1, (int) $get('personas')));
            if ($camas > 0 && $camas <= $unidad->camas_extra) {
                $avisos[] = __('ui.reservas.aviso_camas_extra', ['camas' => $camas]);
            }
        }

        return $avisos === [] ? null : implode(' ', $avisos);
    }

    private static function textoPresupuesto(Get $get, ?Reserva $record): string
    {
        $presupuesto = self::presupuesto($get);
        if ($presupuesto === null || $presupuesto->cotizacion === null) {
            return __('ui.reservas.sin_tarifa');
        }
        $moneda = $record->moneda ?? CampoDinero::moneda($get('propiedad_id'));
        $texto = __('ui.reservas.precio_tarifa', [
            'tarifa' => $presupuesto->cotizacion->tarifa,
            'precio' => Formato::dinero($presupuesto->total(), $moneda),
        ]);
        if ($presupuesto->cotizacion->estanciaLarga !== null) {
            $texto .= ' '.__('estancias.motor.aplicado.'.$presupuesto->cotizacion->estanciaLarga).'.';
        }
        if ($presupuesto->totalCargos() > 0) {
            $texto .= ' '.__('ui.reservas.incluye_cargos', ['importe' => Formato::dinero($presupuesto->totalCargos(), $moneda)]);
        }
        if ($presupuesto->impuestosAdicionales() > 0) {
            $texto .= ' '.__('ui.reservas.mas_impuestos', ['importe' => Formato::dinero($presupuesto->impuestosAdicionales(), $moneda)]);
        }
        if ($presupuesto->impuestosIncluidos() > 0) {
            $texto .= ' '.__('ui.reservas.impuestos_incluidos', ['importe' => Formato::dinero($presupuesto->impuestosIncluidos(), $moneda)]);
        }

        return $texto;
    }

    /**
     * @return array<int, string>
     */
    private static function buscarHuespedes(int $propiedadId, string $busqueda): array
    {
        $texto = '%'.trim($busqueda).'%';

        return Limites::huespedesVisibles(Huesped::query(), Acceso::usuarioActual())->where('propiedad_id', $propiedadId)
            ->where(fn (Builder $q): Builder => $q->where(fn (Builder $n): Builder => $n->conNombre($busqueda))
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
