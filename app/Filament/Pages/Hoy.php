<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\EstadoTarea;
use App\Enums\TipoTarea;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Bitacora\NotaBitacoraResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\NotaBitacora;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorEstancias;
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
        $this->cambiar($reservaId, fn (GestorEstancias $g, Reserva $r) => $g->registrarEntrada($r, Acceso::usuarioActual()), __('ui.reservas.entrada_ok'));
    }

    public function registrarSalida(int $reservaId): void
    {
        $this->cambiar($reservaId, fn (GestorEstancias $g, Reserva $r) => $g->registrarSalida($r, Acceso::usuarioActual()), __('ui.reservas.salida_ok'));
    }

    /**
     * Dónde se puede registrar que alguien llegó o salió: las independientes con permiso de reservas y, desde la
     * 1.0.40, también las subordinadas, con una marca propia (D-153).
     *
     * @return list<int>
     */
    public function propiedadesMarcables(): array
    {
        return array_values(array_intersect($this->propiedadesElegidas(), Acceso::propiedades(Acceso::usuarioActual(), Acceso::RESERVAS)));
    }

    /**
     * Lo pendiente de cobro de las reservas del día y las unidades por limpiar. Lo primero, solo donde se pueden ver datos de dinero.
     *
     * @param  array<string, Collection<int, Reserva>>  $listas
     * @return array{pendiente: array<string, int>, por_limpiar: int|null}
     */
    public function cifrasDelDia(array $listas): array
    {
        $conDatos = Acceso::propiedades(Acceso::usuarioActual(), Acceso::DATOS);
        $pendiente = [];
        foreach ($listas as $lista) {
            foreach ($lista as $reserva) {
                if ($reserva->pendiente > 0 && in_array($reserva->propiedad_id, $conDatos, true)) {
                    $pendiente[$reserva->id] = [$reserva->moneda, $reserva->pendiente];
                }
            }
        }
        $porMoneda = [];
        foreach ($pendiente as [$moneda, $importe]) {
            $porMoneda[$moneda] = ($porMoneda[$moneda] ?? 0) + $importe;
        }
        $limpieza = array_values(array_filter(TipoTarea::cases(), fn (TipoTarea $t): bool => $t->esLimpieza()));

        return [
            'pendiente' => $porMoneda,
            'por_limpiar' => ! Modulos::activo(Modulo::Limpieza) ? null : Tarea::query()->whereIn('propiedad_id', $this->propiedadesElegidas())
                ->whereIn('tipo', array_map(fn (TipoTarea $t): string => $t->value, $limpieza))
                ->whereIn('estado', [EstadoTarea::Pendiente->value, EstadoTarea::EnCurso->value])
                ->where('fecha', '<=', $this->fechaElegida()->format('Y-m-d'))->count(),
        ];
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
     * @param  callable(GestorEstancias, Reserva): mixed  $accion
     */
    private function cambiar(int $reservaId, callable $accion, string $mensaje): void
    {
        $reserva = Limites::reservasVisibles(Reserva::query()->whereIn('propiedad_id', $this->propiedadesMarcables()), Acceso::usuarioActual())->findOrFail($reservaId);
        try {
            $accion(app(GestorEstancias::class), $reserva);
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
