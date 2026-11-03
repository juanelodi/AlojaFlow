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
use App\Servicios\Disponibilidad as ServicioDisponibilidad;
use App\Servicios\Operacion;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * Unidades libres por fechas y personas, en todas las propiedades (M2).
 */
class Disponibilidad extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.disponibilidad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static ?int $navigationSort = 3;

    public string $llegada = '';

    public string $salida = '';

    public int $personas = 2;

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
        return __('ui.disponibilidad.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.disponibilidad.titulo');
    }

    public function mount(): void
    {
        $hoy = Operacion::hoyDe($this->propiedadUnica());
        $this->llegada = $hoy->format('Y-m-d');
        $this->salida = $hoy->addDay()->format('Y-m-d');
    }

    public function error(): ?string
    {
        try {
            $llegada = CarbonImmutable::createFromFormat('!Y-m-d', $this->llegada);
            $salida = CarbonImmutable::createFromFormat('!Y-m-d', $this->salida);
        } catch (Throwable) {
            return __('ui.disponibilidad.fechas_invalidas');
        }
        if (! $llegada || ! $salida || $salida->lte($llegada)) {
            return __('ui.disponibilidad.fechas_invalidas');
        }
        if ($llegada->diffInDays($salida) > 366 || $this->personas < 1 || $this->personas > 100) {
            return __('ui.disponibilidad.fechas_invalidas');
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, \App\Models\Unidad>>
     */
    public function libres(): \Illuminate\Support\Collection
    {
        if ($this->error() !== null) {
            return collect();
        }

        return ServicioDisponibilidad::libres($this->propiedadesElegidas(), $this->llegada, $this->salida, $this->personas)
            ->groupBy('propiedad_id')->toBase();
    }

    public function noches(): int
    {
        return $this->error() === null
            ? (int) CarbonImmutable::parse($this->llegada)->diffInDays(CarbonImmutable::parse($this->salida))
            : 0;
    }
}
