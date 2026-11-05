<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Bitacora\NotaBitacoraResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\NotaBitacora;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Url;
use Throwable;

/**
 * La jornada en una pantalla: llegan, salen y siguen alojados (M3).
 */
class Hoy extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.hoy';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?int $navigationSort = 1;

    #[Url]
    public ?string $fecha = null;

    protected static function permiso(): string
    {
        return Acceso::OPERACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.hoy.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.hoy.titulo');
    }

    public function mount(): void
    {
        $this->fecha ??= Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
    }

    public function fechaElegida(): CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->fecha) ?: Operacion::hoyDe($this->propiedadUnica());
        } catch (Throwable) {
            return Operacion::hoyDe($this->propiedadUnica());
        }
    }

    public function moverDia(int $dias): void
    {
        $this->fecha = $this->fechaElegida()->addDays($dias)->format('Y-m-d');
    }

    public function irAHoy(): void
    {
        $this->fecha = Operacion::hoyDe($this->propiedadUnica())->format('Y-m-d');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('nuevaReserva')->label(__('ui.reservas.nueva'))->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => ReservaResource::getUrl('create'))
                ->visible(fn (): bool => ReservaResource::canCreate()),
        ];
    }

    public function registrarEntrada(int $reservaId): void
    {
        $this->cambiar($reservaId, fn (GestorReservas $g, Reserva $r) => $g->registrarEntrada($r), __('ui.reservas.entrada_ok'));
    }

    public function registrarSalida(int $reservaId): void
    {
        $this->cambiar($reservaId, fn (GestorReservas $g, Reserva $r) => $g->registrarSalida($r), __('ui.reservas.salida_ok'));
    }

    /**
     * Lo importante de la bitácora, hasta que alguien lo resuelva.
     *
     * @return Collection<int, NotaBitacora>
     */
    public function notasImportantes(): Collection
    {
        if (! NotaBitacoraResource::canViewAny()) {
            return new Collection();
        }

        return NotaBitacora::query()->with('autor')->whereIn('propiedad_id', array_intersect($this->propiedadesElegidas(), NotaBitacoraResource::propiedadesGestionables()))
            ->where('importante', true)->whereNull('resuelta_en')->orderByDesc('creado_en')->limit(5)->get();
    }

    /**
     * @param  callable(GestorReservas, Reserva): mixed  $accion
     */
    private function cambiar(int $reservaId, callable $accion, string $mensaje): void
    {
        $reserva = Limites::reservasVisibles(Reserva::query()->whereIn('propiedad_id', ReservaResource::propiedadesGestionables()), Acceso::usuarioActual())->findOrFail($reservaId);
        try {
            $accion(app(GestorReservas::class), $reserva);
            Notification::make()->success()->title($mensaje)->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->send();
        }
    }

    /**
     * @return array<string, \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reserva>>
     */
    public function listas(): array
    {
        return Operacion::dia($this->propiedadesElegidas(), $this->fechaElegida()->format('Y-m-d'));
    }
}
