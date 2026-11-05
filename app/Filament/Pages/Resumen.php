<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Models\Aviso;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Modulos\PrimerosPasos;
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
     * Quien instaló entra por primera vez: primero las preguntas (D-069).
     */
    public function mount(): void
    {
        if (Modulos::faltanPreguntas() && Acceso::esSuper(Acceso::usuarioActual())) {
            $this->redirect(PrimerasPreguntas::getUrl());
        }
    }

    public function atenderAviso(int $id): void
    {
        Aviso::query()->whereKey($id)->whereIn('propiedad_id', Acceso::propiedades(Acceso::usuarioActual(), Acceso::DATOS))
            ->whereIn('tipo', Aviso::SE_CIERRAN_A_MANO)->update(['clave_unica' => null, 'resuelto_en' => now()]);
    }

    /**
     * Quien administra todo sin verificación en dos pasos es la cuenta que más conviene proteger.
     */
    public function faltaDosPasos(): bool
    {
        $usuario = Acceso::usuarioActual();

        return $usuario?->es_administrador === true && ! ModoDemo::activo() && blank($usuario->getAppAuthenticationSecret());
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

    /**
     * @return list<array{clave: string, hecho: bool, url: string|null, comando: string|null}>
     */
    public function primerosPasos(): array
    {
        return PrimerosPasos::mostrar(Acceso::usuarioActual());
    }

    public function ocultarPrimerosPasos(): void
    {
        if (Acceso::usuarioActual()?->es_administrador) {
            PrimerosPasos::ocultar();
        }
    }

    /**
     * @return list<Modulo>
     */
    public function modulosNuevos(): array
    {
        return Acceso::esSuper(Acceso::usuarioActual()) ? Modulos::nuevos() : [];
    }

    public function puedeConfigurar(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }
}
