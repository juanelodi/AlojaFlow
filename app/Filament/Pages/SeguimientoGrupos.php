<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Servicios\SeguimientoGrupos as Consulta;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * El panel de los grupos: lo que hay que hacer hoy con todos, sin abrirlos uno por uno.
 */
class SeguimientoGrupos extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.seguimiento-grupos';

    protected static ?string $slug = 'seguimiento-de-grupos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 4;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Grupos;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function canAccess(): bool
    {
        return GrupoResource::propiedadesGestionables() !== [];
    }

    /**
     * @return list<int>
     */
    public function propiedadesVisibles(): array
    {
        return GrupoResource::propiedadesGestionables();
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.reservas');
    }

    public static function getNavigationLabel(): string
    {
        return __('grupos.seguimiento.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('grupos.seguimiento.titulo');
    }

    /**
     * @return array<string, mixed>
     */
    public function resumen(): array
    {
        return Consulta::resumen($this->propiedadesElegidas());
    }
}
