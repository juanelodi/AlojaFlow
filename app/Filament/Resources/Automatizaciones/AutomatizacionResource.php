<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Automatizaciones;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoReserva;
use App\Filament\Resources\Automatizaciones\Pages\CreateAutomatizacion;
use App\Filament\Resources\Automatizaciones\Pages\EditAutomatizacion;
use App\Filament\Resources\Automatizaciones\Pages\ListAutomatizaciones;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Models\Automatizacion;
use App\Models\Plantilla;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Qué mensajes salen solos: al reservar, antes de llegar, después de salir…
 */
class AutomatizacionResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Automatizacion::class;

    protected static ?string $slug = 'automatizaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?int $navigationSort = 3;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Mensajes;
    }

    protected static function permiso(): string
    {
        return Acceso::AJUSTES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.comunicacion');
    }

    public static function getModelLabel(): string
    {
        return __('mensajes.automatizaciones.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('mensajes.automatizaciones.modelos');
    }

    private static function disparador(Get $get): ?DisparadorAutomatizacion
    {
        $valor = $get('disparador');

        return $valor instanceof DisparadorAutomatizacion ? $valor : DisparadorAutomatizacion::tryFrom((string) $valor);
    }

    protected static function referencias(): array
    {
        return ['plantilla_id' => Plantilla::class];
    }

    /**
     * @return array<string, string>
     */
    private static function acciones(): array
    {
        return array_filter(AccionAutomatizacion::opciones(), fn (string $accion): bool => match ($accion) {
            AccionAutomatizacion::CrearTarea->value => Modulos::activo(Modulo::Limpieza),
            AccionAutomatizacion::NotaBitacora->value => Modulos::activo(Modulo::Bitacora),
            default => true,
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @return array<string, string>
     */
    private static function disparadores(): array
    {
        return array_filter(DisparadorAutomatizacion::opciones(), fn (string $disparador): bool => Modulos::activo(Modulo::EstanciasLargas) || ! DisparadorAutomatizacion::from($disparador)->esDeCuota(), ARRAY_FILTER_USE_KEY);
    }

    private static function esCorreo(Get $get): bool
    {
        $accion = $get('accion');

        return ($accion instanceof AccionAutomatizacion ? $accion : AccionAutomatizacion::tryFrom((string) $accion))?->esCorreo() ?? true;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->description(__('mensajes.automatizaciones.ayuda'))->columnSpanFull()->columns(['default' => 1, 'md' => 2])->schema([
                self::campoPropiedad(),
                TextInput::make('nombre')->label(__('mensajes.automatizaciones.nombre'))->required()->maxLength(100),
                Select::make('disparador')->label(__('mensajes.automatizaciones.disparador'))->options(self::disparadores())->required()->native(false)->live(),
                TextInput::make('dias')->label(__('mensajes.automatizaciones.dias'))->integer()->minValue(0)->maxValue(365)->default(1)
                    ->required(fn (Get $get): bool => (bool) self::disparador($get)?->usaDias())
                    ->visible(fn (Get $get): bool => (bool) self::disparador($get)?->usaDias()),
                TimePicker::make('hora')->label(__('mensajes.automatizaciones.hora'))->seconds(false)
                    ->visible(fn (Get $get): bool => (bool) self::disparador($get)?->esDeTiempo()),
                Select::make('condiciones.estado')->label(__('mensajes.automatizaciones.estado'))->native(false)->placeholder(__('mensajes.automatizaciones.cualquier_estado'))
                    ->options([EstadoReserva::Confirmada->value => EstadoReserva::Confirmada->etiqueta(), EstadoReserva::SinConfirmar->value => EstadoReserva::SinConfirmar->etiqueta()]),
                Select::make('condiciones.saldo')->label(__('operacion.automatizaciones.saldo'))->native(false)->placeholder(__('operacion.automatizaciones.saldo_cualquiera'))
                    ->options(['con_saldo' => __('operacion.automatizaciones.con_saldo'), 'sin_saldo' => __('operacion.automatizaciones.sin_saldo')]),
                TextInput::make('condiciones.canal')->label(__('operacion.automatizaciones.canal'))->helperText(__('operacion.automatizaciones.canal_ayuda'))->maxLength(100),
                Toggle::make('condiciones.llegada_hecha')->label(__('operacion.automatizaciones.llegada_hecha')),
                Toggle::make('condiciones.contrato_aceptado')->label(__('operacion.automatizaciones.contrato_aceptado')),
                Toggle::make('condiciones.esperar')->label(__('operacion.automatizaciones.esperar'))->helperText(__('operacion.automatizaciones.esperar_ayuda'))
                    ->visible(fn (Get $get): bool => self::disparador($get) === DisparadorAutomatizacion::AntesLlegada),
                Select::make('accion')->label(__('mensajes.automatizaciones.accion'))->options(self::acciones())->required()->native(false)->live()->default(AccionAutomatizacion::CorreoHuesped->value),
                TextInput::make('destino')->label(__('mensajes.automatizaciones.destino'))->email()->maxLength(255)
                    ->required(fn (Get $get): bool => in_array($get('accion'), [AccionAutomatizacion::CorreoFijo, AccionAutomatizacion::CorreoFijo->value], true))
                    ->visible(fn (Get $get): bool => in_array($get('accion'), [AccionAutomatizacion::CorreoFijo, AccionAutomatizacion::CorreoFijo->value], true)),
                Select::make('plantilla_id')->label(__('mensajes.automatizaciones.plantilla'))->helperText(fn (Get $get): ?string => self::esCorreo($get) ? null : __('operacion.automatizaciones.plantilla_opcional'))->required(fn (Get $get): bool => self::esCorreo($get))->native(false)
                    ->options(fn (Get $get): array => Plantilla::query()->where('propiedad_id', self::propiedadDe($get))->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Toggle::make('activa')->label(__('mensajes.automatizaciones.activa'))->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'plantilla']))
            ->defaultSort('nombre')
            ->columns([
                TextColumn::make('nombre')->label(__('mensajes.automatizaciones.nombre'))->searchable(),
                self::columnaPropiedad(),
                TextColumn::make('disparador')->label(__('mensajes.automatizaciones.disparador'))->formatStateUsing(fn (DisparadorAutomatizacion $state): string => $state->etiqueta()),
                TextColumn::make('plantilla.nombre')->label(__('mensajes.automatizaciones.plantilla'))->placeholder('—')->toggleable(),
                IconColumn::make('activa')->label(__('mensajes.automatizaciones.activa'))->boolean(),
            ])
            ->emptyStateHeading(__('mensajes.automatizaciones.vacio'))
            ->recordActions([EditAction::make()->visible(fn (Automatizacion $record): bool => self::canEdit($record))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutomatizaciones::route('/'),
            'create' => CreateAutomatizacion::route('/create'),
            'edit' => EditAutomatizacion::route('/{record}/edit'),
        ];
    }
}
