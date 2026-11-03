<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use App\Servicios\Operacion;
use BackedEnum;
use Filament\Pages\Dashboard;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * Inicio: una tarjeta por propiedad con el día, lo pendiente y los avisos.
 */
class Resumen extends Dashboard
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.resumen';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -10;

    protected static function permiso(): string
    {
        return Acceso::OPERACION;
    }

    /**
     * El inicio siempre se puede abrir: sin propiedades muestra cómo empezar.
     */
    public static function canAccess(): bool
    {
        return Acceso::usuarioActual() !== null;
    }

    public static function getNavigationLabel(): string
    {
        return __('ui.resumen.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.resumen.titulo');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tarjetas(): array
    {
        return Propiedad::query()->with('principal')->whereIn('id', $this->propiedadesVisibles())->orderBy('nombre')->get()
            ->map(fn (Propiedad $p): array => Operacion::resumen($p))->values()->all();
    }

    public function puedeConfigurar(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }
}
