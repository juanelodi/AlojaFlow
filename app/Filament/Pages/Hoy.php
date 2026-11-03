<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
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
     * @return array<string, \Illuminate\Database\Eloquent\Collection<int, \App\Models\Reserva>>
     */
    public function listas(): array
    {
        return Operacion::dia($this->propiedadesElegidas(), $this->fechaElegida()->format('Y-m-d'));
    }
}
