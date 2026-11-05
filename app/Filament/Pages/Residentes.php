<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Seguridad\Acceso;
use App\Servicios\Residentes as ListaResidentes;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Quienes viven aquí una temporada, con lo que deben y si firmaron el reglamento (D-112).
 */
class Residentes extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.residentes';

    protected static ?string $slug = 'residentes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 8;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Camping;
    }

    protected static function permiso(): string
    {
        return Acceso::RESERVAS;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('camping.residentes.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('camping.residentes.titulo');
    }

    /**
     * @return list<array{reserva: \App\Models\Reserva, por_pagar: int, reglamento: string|null, whatsapp: string|null}>
     */
    public function filas(): array
    {
        return ListaResidentes::lista($this->propiedadesElegidas());
    }

    public function urlMensajes(): ?string
    {
        return Modulos::activo(Modulo::Mensajes) && MensajesPeriodo::canAccess() ? MensajesPeriodo::getUrl(['por' => 'residentes']) : null;
    }
}
