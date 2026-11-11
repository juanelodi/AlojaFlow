<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Grupos;

use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPrecioGrupo;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Base\RecursoDePropiedad;
use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Grupos\Pages\EditGrupo;
use App\Filament\Resources\Grupos\Pages\ListGrupos;
use App\Filament\Resources\Grupos\Pages\ViewGrupo;
use App\Filament\Resources\Grupos\RelationManagers\ComprobantesRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\CuotasRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\HabitacionesRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\MensajesRelationManager;
use App\Grupos\CalendarioPagos;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\ReservaNoche;
use App\Models\Unidad;
use App\Modulos\Modulo;
use App\Reservas\GestorGrupos;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Formato;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Reservas de grupo: varias unidades con un mismo coordinador y un solo saldo.
 */
class GrupoResource extends RecursoDePropiedad
{
    protected static ?string $model = Grupo::class;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?string $slug = 'grupos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 3;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Grupos;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    /**
     * Quien solo ve lo suyo no ve los grupos: juntan reservas de todos.
     */
    public static function propiedadesGestionables(): array
    {
        return array_values(array_diff(parent::propiedadesGestionables(), Limites::propiedadesSoloPropias(Acceso::usuarioActual())));
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.reservas');
    }

    public static function getModelLabel(): string
    {
        return __('ui.grupos.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.grupos.modelos');
    }

    public static function canView(Model $record): bool
    {
        return in_array((int) $record->getAttribute('propiedad_id'), static::propiedadesGestionables(), true);
    }

    public static function canEdit(Model $record): bool
    {
        return self::canView($record);
    }

    /**
     * Solo uno cancelado, y solo quien administra: con él se va su historial de pagos por confirmar y sus mensajes.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Grupo && $record->estado === EstadoGrupo::Cancelado && self::canView($record)
            && Acceso::puede(Acceso::usuarioActual(), Acceso::AJUSTES, $record->propiedad_id);
    }

    protected static function referencias(): array
    {
        return ['coordinador_id' => Huesped::class];
    }

    public static function getRelations(): array
    {
        return [HabitacionesRelationManager::class, CuotasRelationManager::class, ComprobantesRelationManager::class, MensajesRelationManager::class];
    }

    public static function form(Schema $schema): Schema
    {
        // Se pregunta una vez por cada casilla.
        $memoria = [];
        $ocupadas = function (Get $get) use (&$memoria): array {
            return $memoria[$get('propiedad_id').'|'.$get('llegada').'|'.$get('salida')] ??= self::ocupadas($get);
        };

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('ui.grupos.nombre'))->helperText(__('ui.grupos.nombre_ayuda'))->required()->maxLength(150),
                Select::make('coordinador_id')->label(__('ui.grupos.coordinador'))->required()->native(false)->searchable()
                    ->getSearchResultsUsing(fn (string $search, Get $get): array => Limites::huespedesVisibles(Huesped::query(), Acceso::usuarioActual())->where('propiedad_id', self::propiedadDe($get))
                        ->conNombre($search)
                        ->limit(30)->get()->mapWithKeys(fn (Huesped $h): array => [$h->id => $h->nombreCompleto()])->all())
                    ->getOptionLabelUsing(fn (mixed $value, Get $get): ?string => Huesped::query()->where('propiedad_id', self::propiedadDe($get))->find((int) $value)?->nombreCompleto()),
                Select::make('estado')->label(__('ui.comun.estado'))->required()->native(false)->visibleOn('create')
                    ->options([
                        EstadoReserva::Confirmada->value => EstadoGrupo::Confirmado->etiqueta(),
                        EstadoReserva::SinConfirmar->value => EstadoGrupo::Tentativo->etiqueta(),
                    ])
                    ->default(EstadoReserva::Confirmada->value),
                DatePicker::make('llegada')->label(__('ui.comun.llegada'))->required()->live(onBlur: true)->extraInputAttributes(RangoFechas::llegada())->visibleOn('create'),
                DatePicker::make('salida')->label(__('ui.comun.salida'))->required()->after('llegada')->extraInputAttributes(RangoFechas::fin('estancia'))->live(onBlur: true)->visibleOn('create'),
                TextInput::make('personas')->label(__('ui.grupos.personas_por_unidad'))->numeric()->minValue(1)->default(2)->required()->visibleOn('create'),
                CheckboxList::make('unidades')->label(__('ui.grupos.unidades'))->helperText(__('ui.grupos.unidades_ayuda'))->required()->columnSpanFull()->columns(['default' => 1, 'md' => 2, 'lg' => 3])
                    ->options(fn (Get $get): array => self::opcionesUnidades($get, $ocupadas($get)))
                    ->disableOptionWhen(fn (string $value, Get $get): bool => isset($ocupadas($get)[(int) $value]))
                    ->bulkToggleable()
                    ->selectAllAction(fn ($action) => $action->label(__('grupos.alta.todas_libres')))
                    ->deselectAllAction(fn ($action) => $action->label(__('grupos.alta.ninguna')))
                    ->visibleOn('create'),
                ColorPicker::make('color')->label(__('grupos.alta.color'))->helperText(__('grupos.alta.color_ayuda'))->regex('/^#[0-9a-fA-F]{6}$/'),
                Textarea::make('notas')->label(__('ui.huespedes.notas'))->rows(2)->maxLength(5000)->columnSpanFull(),
            ]),
            Section::make(__('grupos.precio.titulo'))->description(__('grupos.precio.ayuda'))->visibleOn('edit')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                Select::make('modo_precio')->label(__('grupos.precio.modo'))->options(ModoPrecioGrupo::opciones())->required()->native(false)->live()->columnSpanFull(),
                TextInput::make('precio_total')->label(__('grupos.precio.total'))->helperText(__('grupos.precio.total_ayuda'))->numeric()->minValue(0)->step('any')
                    ->required(fn (Get $get): bool => self::modo($get) === ModoPrecioGrupo::Total)
                    ->visible(fn (Get $get): bool => self::modo($get) === ModoPrecioGrupo::Total),
                Repeater::make('precios_capacidad')->label(__('grupos.precio.por_capacidad'))->defaultItems(1)->columns(2)->columnSpanFull()
                    ->addActionLabel(__('grupos.precio.agregar_capacidad'))
                    ->visible(fn (Get $get): bool => self::modo($get) === ModoPrecioGrupo::PorCapacidad)
                    ->schema([
                        TextInput::make('capacidad')->label(__('grupos.precio.capacidad'))->numeric()->integer()->minValue(1)->required()->distinct(),
                        TextInput::make('precio')->label(__('grupos.precio.precio_noche'))->numeric()->minValue(0)->step('any')->required(),
                    ]),
                TextInput::make('gratis_cada')->label(__('grupos.precio.gratis_cada'))->helperText(__('grupos.precio.gratis_cada_ayuda'))->numeric()->integer()->minValue(2),
                TextInput::make('descuento_porcentaje')->label(__('grupos.precio.descuento'))->numeric()->minValue(0)->maxValue(100)->step('any')->suffix('%')->required(),
                TextInput::make('extras')->label(__('grupos.precio.extras'))->helperText(__('grupos.precio.extras_ayuda'))->numeric()->minValue(0)->step('any')->required(),
            ]),
            Section::make(__('grupos.lista.titulo'))->visibleOn('edit')->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                DatePicker::make('fecha_limite_lista')->label(__('grupos.lista.fecha_limite'))->live(),
                Toggle::make('liberar_sin_responsable')->label(__('grupos.lista.liberar'))->helperText(__('grupos.lista.liberar_ayuda'))
                    ->visible(fn (Get $get): bool => filled($get('fecha_limite_lista'))),
                TextInput::make('dias_acceso_tras_salida')->label(__('grupos.acceso.dias'))->helperText(__('grupos.acceso.dias_ayuda'))->numeric()->integer()->minValue(0)->maxValue(365)->required(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $dinero = fn (string $campo): \Closure => function (Grupo $record) use ($campo): string {
            $saldo = app(GestorGrupos::class)->saldo($record);

            return Formato::dinero($saldo[$campo], $saldo['moneda']);
        };

        return $schema->components([
            Section::make()->columnSpanFull()->columns(['default' => 1, 'sm' => 2, 'lg' => 4])->schema([
                TextEntry::make('codigo')->label(__('ui.grupos.codigo'))->copyable(),
                TextEntry::make('nombre')->label(__('ui.grupos.nombre')),
                TextEntry::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->formatStateUsing(fn (Grupo $record): string => $record->estado->etiqueta())
                    ->color(fn (Grupo $record): string => self::colorEstado($record->estado)),
                TextEntry::make('coordinador')->label(__('ui.grupos.coordinador'))
                    ->state(fn (Grupo $record): ?string => $record->coordinador?->nombreCompleto())->placeholder('—'),
                TextEntry::make('propiedad.nombre')->label(__('ui.comun.propiedad')),
                TextEntry::make('saldo_total')->label(__('ui.comun.total'))->state($dinero('total')),
                TextEntry::make('saldo_pagado')->label(__('ui.comun.pagado'))->state($dinero('pagado')),
                TextEntry::make('saldo_por_confirmar')->label(__('grupos.publico.por_confirmar'))->state($dinero('por_confirmar')),
                TextEntry::make('saldo_pendiente')->label(__('ui.comun.pendiente'))->state($dinero('pendiente'))->weight('bold'),
                TextEntry::make('cuadre')->label(__('grupos.cuotas.titulo'))->color('warning')->columnSpan(['default' => 1, 'sm' => 2])
                    ->visible(fn (Grupo $record): bool => ! CalendarioPagos::de($record)['cuadra'])
                    ->state(function (Grupo $record): string {
                        $calendario = CalendarioPagos::de($record);
                        $moneda = $record->propiedad->moneda;

                        return __('grupos.cuotas.no_cuadra', ['suma' => Formato::dinero($calendario['suma'], $moneda), 'total' => Formato::dinero($calendario['total'], $moneda)]);
                    }),
                TextEntry::make('notas')->label(__('ui.huespedes.notas'))->placeholder('—'),
            ]),
            Section::make(__('grupos.historial.titulo'))->columnSpanFull()->collapsed()->schema([
                ViewEntry::make('historial')->hiddenLabel()->view('filament.partes.grupo-historial'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'coordinador'])->withCount('reservas'))
            ->defaultSort('creado_en', 'desc')
            ->columns([
                TextColumn::make('codigo')->label(__('ui.grupos.codigo'))->searchable(),
                TextColumn::make('nombre')->label(__('ui.grupos.nombre'))->searchable(),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->formatStateUsing(fn (Grupo $record): string => $record->estado->etiqueta())
                    ->color(fn (Grupo $record): string => self::colorEstado($record->estado)),
                self::columnaPropiedad(),
                TextColumn::make('coordinador_id')->label(__('ui.grupos.coordinador'))
                    ->formatStateUsing(fn (Grupo $record): string => $record->coordinador?->nombreCompleto() ?? '—'),
                TextColumn::make('reservas_count')->label(__('ui.reservas.modelos'))->alignEnd(),
                TextColumn::make('pendiente')->label(__('ui.comun.pendiente'))->alignEnd()
                    ->state(function (Grupo $record): string {
                        $saldo = app(GestorGrupos::class)->saldo($record);

                        return Formato::dinero($saldo['pendiente'], $saldo['moneda']);
                    }),
            ])
            ->filters([SelectFilter::make('estado')->label(__('ui.comun.estado'))->options(EstadoGrupo::opciones())])
            ->emptyStateHeading(__('ui.grupos.vacio'))
            ->recordActions([ViewAction::make()])
            ->recordUrl(fn (Grupo $record): string => self::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrupos::route('/'),
            'create' => CreateGrupo::route('/create'),
            'view' => ViewGrupo::route('/{record}'),
            'edit' => EditGrupo::route('/{record}/edit'),
        ];
    }

    public static function colorEstado(EstadoGrupo $estado): string
    {
        return match ($estado) {
            EstadoGrupo::Confirmado => 'success',
            EstadoGrupo::Tentativo => 'warning',
            EstadoGrupo::Cancelado => 'gray',
        };
    }

    private static function modo(Get $get): ?ModoPrecioGrupo
    {
        $modo = $get('modo_precio');

        return $modo instanceof ModoPrecioGrupo ? $modo : ModoPrecioGrupo::tryFrom((string) $modo);
    }

    /**
     * @param  array<int, string>  $ocupadas
     * @return array<int, string>
     */
    private static function opcionesUnidades(Get $get, array $ocupadas): array
    {
        if (blank($get('propiedad_id'))) {
            return [];
        }
        $opciones = [];
        foreach (Unidad::query()->where('propiedad_id', self::propiedadDe($get))->where('activa', true)->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre']) as $unidad) {
            $opciones[$unidad->id] = isset($ocupadas[$unidad->id])
                ? __('grupos.alta.ocupada', ['unidad' => $unidad->nombre, 'quien' => $ocupadas[$unidad->id]])
                : $unidad->nombre;
        }

        return $opciones;
    }

    /**
     * Las que no se pueden elegir en esas fechas, y quién las tiene.
     *
     * @return array<int, string>
     */
    private static function ocupadas(Get $get): array
    {
        try {
            $llegada = filled($get('llegada')) ? CarbonImmutable::parse((string) $get('llegada')) : null;
            $salida = filled($get('salida')) ? CarbonImmutable::parse((string) $get('salida')) : null;
        } catch (Throwable) {
            return [];
        }
        if ($llegada === null || $salida === null || $salida->lte($llegada)) {
            return [];
        }
        $ocupadas = [];
        $noches = ReservaNoche::query()->with('reserva.titular')->where('propiedad_id', self::propiedadDe($get))
            ->where('fecha', '>=', $llegada->format('Y-m-d'))->where('fecha', '<', $salida->format('Y-m-d'))->get();
        foreach ($noches as $noche) {
            /** @var ReservaNoche $noche */
            $ocupadas[$noche->unidad_id] ??= $noche->reserva->titular?->nombreCompleto() ?? __('ui.calendario.bloqueo');
        }

        return $ocupadas;
    }
}
