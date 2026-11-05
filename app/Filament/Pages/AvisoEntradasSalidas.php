<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Resources\Omisiones\OmisionResource;
use App\Limpieza\AvisoEquipo;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

/**
 * Aviso de entradas y salidas para el equipo (D-074). El texto se puede retocar antes de mandarlo; cambiar una opción lo vuelve a armar.
 */
class AvisoEntradasSalidas extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.aviso-entradas-salidas';

    protected static ?string $slug = 'aviso';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 4;

    public string $desde = '';

    public ?int $dias = 7;

    public bool $nombre = false;

    public bool $personas = false;

    public bool $origen = false;

    public bool $noches = false;

    public bool $telefono = false;

    public bool $comentario = false;

    public bool $limpieza = true;

    public string $texto = '';

    protected static function modulo(): ?Modulo
    {
        return Modulo::AvisoEquipo;
    }

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
        return __('equipo.aviso.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('equipo.aviso.titulo');
    }

    public function mount(): void
    {
        $this->desde = ($this->propiedad()?->hoy() ?? CarbonImmutable::now())->format('Y-m-d');
        $this->armar();
    }

    public function updated(string $propiedad): void
    {
        if ($propiedad !== 'texto') {
            $this->armar();
        }
    }

    /**
     * Una sola propiedad: es un parte para el equipo de un lugar. Sin elegir, la primera.
     */
    public function propiedad(): ?Propiedad
    {
        $elegidas = $this->propiedadesElegidas();

        return $elegidas === [] ? null : Propiedad::query()->find($elegidas[0]);
    }

    /**
     * Limpieza y mantenimiento ven unidad, fechas y nombre, no teléfonos ni comentarios (D-055).
     */
    public function puedeVerContacto(): bool
    {
        $propiedad = $this->propiedad();

        return $propiedad !== null && Acceso::puede(Acceso::usuarioActual(), Acceso::DATOS, $propiedad->id);
    }

    public function urlOmitidos(): ?string
    {
        return OmisionResource::canViewAny() ? OmisionResource::getUrl() : null;
    }

    private function armar(): void
    {
        $propiedad = $this->propiedad();
        if ($propiedad === null) {
            $this->texto = '';

            return;
        }
        try {
            $desde = CarbonImmutable::createFromFormat('Y-m-d', $this->desde, $propiedad->zona_horaria)?->startOfDay() ?? $propiedad->hoy();
        } catch (Throwable) {
            $desde = $propiedad->hoy();
        }
        $this->dias = max(1, min($this->dias ?? 7, AvisoEquipo::DIAS_MAXIMOS));
        $contacto = $this->puedeVerContacto();
        $this->texto = app(AvisoEquipo::class)->generar($propiedad, $desde, $this->dias, [
            'nombre' => $this->nombre,
            'personas' => $this->personas,
            'origen' => $this->origen,
            'noches' => $this->noches,
            'telefono' => $this->telefono && $contacto,
            'comentario' => $this->comentario && $contacto,
            'limpieza' => $this->limpieza,
        ]);
    }
}
