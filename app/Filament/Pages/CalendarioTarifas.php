<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Campos\RangoFechas;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Reservas\GestorPreciosTarifa;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\CalendarioTarifas as ServicioCalendarioTarifas;
use App\Servicios\Operacion;
use App\Support\Dinero;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Tarifas por noche en un calendario, por propiedad (D-148): una fila por tarifa y una columna por día, como las tablas de precios de los PMS.
 * Pulsar un día pone precio a un rango. Solo independientes: en una subordinada, los precios viven en la principal.
 */
class CalendarioTarifas extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.calendario-tarifas';

    protected static ?string $slug = 'calendario-tarifas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?int $navigationSort = 3;

    #[Url]
    public ?string $mes = null;

    protected static function permiso(): string
    {
        return Acceso::CATALOGO;
    }

    public static function canAccess(): bool
    {
        return TarifaResource::canViewAny();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getNavigationLabel(): string
    {
        return __('tarifas_calendario.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('tarifas_calendario.titulo');
    }

    public function mount(): void
    {
        $this->mes ??= Operacion::hoyDe($this->propiedadUnica())->format('Y-m');
    }

    public function inicioMes(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m', (string) $this->mes) ?: Operacion::hoyDe(null)->startOfMonth();
        } catch (Throwable) {
            return Operacion::hoyDe(null)->startOfMonth();
        }
    }

    public function moverMes(int $meses): void
    {
        $this->mes = $this->inicioMes()->addMonths($meses)->format('Y-m');
    }

    public function irAEsteMes(): void
    {
        $this->mes = Operacion::hoyDe($this->propiedadUnica())->format('Y-m');
    }

    /**
     * @return list<int>
     */
    private function propiedadesConTarifas(): array
    {
        return array_values(array_intersect($this->propiedadesElegidas(), TarifaResource::propiedadesGestionables()));
    }

    /**
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return ServicioCalendarioTarifas::mes($this->propiedadesConTarifas(), $this->inicioMes());
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('verTarifas')->label(__('ui.tarifas.modelos'))->icon(Heroicon::OutlinedCurrencyDollar)->color('gray')->url(fn (): string => TarifaResource::getUrl()),
            Action::make('fijarPrecio')->label(__('tarifas_calendario.fijar'))->icon(Heroicon::OutlinedPencilSquare)
                ->modalDescription(__('tarifas_calendario.fijar_ayuda'))
                ->fillForm(fn (array $arguments): array => [
                    'tarifa_id' => $arguments['tarifa'] ?? null,
                    'desde' => $arguments['fecha'] ?? null,
                    'hasta' => $arguments['fecha'] ?? null,
                    'nombre' => __('tarifas_calendario.nombre_defecto'),
                ])
                ->schema([
                    Select::make('tarifa_id')->label(__('tarifas_calendario.tarifa'))->required()->native(false)->options(fn (): array => $this->tarifasEditables()),
                    DatePicker::make('desde')->extraInputAttributes(RangoFechas::inicio('precio'))->label(__('ui.indicadores.desde'))->required(),
                    DatePicker::make('hasta')->extraInputAttributes(RangoFechas::fin('precio'))->label(__('tarifas_calendario.hasta'))->helperText(__('tarifas_calendario.hasta_ayuda'))->required()->afterOrEqual('desde'),
                    TextInput::make('precio_noche')->label(__('tarifas_calendario.precio_noche'))->numeric()->minValue(0)->step('any')->required(),
                    TextInput::make('precio_fin_semana')->label(__('ui.tarifas.fin_semana'))->helperText(__('ui.tarifas.fin_semana_ayuda'))->numeric()->minValue(0)->step('any'),
                    TextInput::make('estancia_minima')->label(__('ui.tarifas.estancia_minima'))->numeric()->integer()->minValue(1),
                    TextInput::make('nombre')->label(__('ui.tarifas.temporada'))->required()->maxLength(100),
                ])
                ->action(fn (array $data) => $this->fijarPrecio($data)),
        ];
    }

    /**
     * Las derivadas no: sus precios salen de otra, y ahí se cambian.
     *
     * @return array<int, string>
     */
    private function tarifasEditables(): array
    {
        $tarifas = Tarifa::query()->with('propiedad')->whereIn('propiedad_id', TarifaResource::propiedadesGestionables())->whereNull('tarifa_base_id')->where('activa', true)->orderBy('nombre')->get();
        $varias = $tarifas->pluck('propiedad_id')->unique()->count() > 1;

        return $tarifas->mapWithKeys(fn (Tarifa $t): array => [$t->id => $varias ? $t->propiedad->nombre.' · '.$t->nombre : $t->nombre])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fijarPrecio(array $data): void
    {
        $tarifa = Tarifa::query()->whereIn('propiedad_id', TarifaResource::propiedadesGestionables())->findOrFail((int) $data['tarifa_id']);
        /** @var Propiedad $propiedad */
        $propiedad = $tarifa->propiedad;
        try {
            app(GestorPreciosTarifa::class)->fijar(
                $tarifa,
                CarbonImmutable::parse((string) $data['desde']),
                CarbonImmutable::parse((string) $data['hasta']),
                (string) $data['nombre'],
                Dinero::aUnidadMenor((string) $data['precio_noche'], $propiedad->moneda),
                blank($data['precio_fin_semana'] ?? null) ? null : Dinero::aUnidadMenor((string) $data['precio_fin_semana'], $propiedad->moneda),
                blank($data['estancia_minima'] ?? null) ? null : (int) $data['estancia_minima'],
            );
            Notification::make()->success()->title(__('tarifas_calendario.guardado'))->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }
}
