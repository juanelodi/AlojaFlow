<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Seguridad\Acceso;
use App\Servicios\Calendario as ServicioCalendario;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Throwable;

/**
 * Calendario por habitaciones de un mes (M2). En una propiedad independiente, un día libre abre
 * una reserva nueva; en una subordinada es solo consulta y se cambia en la principal.
 */
class Calendario extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.calendario';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    #[Url]
    public ?string $mes = null;

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
        return __('ui.calendario.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.calendario.titulo');
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
     * @return array<string, mixed>
     */
    public function datos(): array
    {
        return ServicioCalendario::mes($this->propiedadesElegidas(), $this->inicioMes());
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

    public function hoy(): CarbonImmutable
    {
        return Operacion::hoyDe($this->propiedadUnica());
    }
}
