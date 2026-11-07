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
use App\Limpieza\FormatoAviso;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
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

    private const DIAS_INICIALES = 7;

    public string $desde = '';

    public string $hasta = '';

    /** @var array<string, mixed> */
    public array $formato = [];

    public bool $nombre = false;

    public bool $personas = false;

    public bool $origen = false;

    public bool $noches = false;

    public bool $telefono = false;

    public bool $comentario = false;

    public bool $limpieza = true;

    public string $texto = '';

    public bool $excedido = false;

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
        $hoy = $this->propiedad()?->hoy() ?? CarbonImmutable::now();
        $this->desde = $hoy->format('Y-m-d');
        $this->hasta = $hoy->addDays(self::DIAS_INICIALES - 1)->format('Y-m-d');
        $this->formato = FormatoAviso::de((int) $this->propiedad()?->id);
        $this->armar();
    }

    public function updated(string $propiedad): void
    {
        if ($propiedad === 'propiedadId') {
            $this->formato = FormatoAviso::de((int) $this->propiedad()?->id);
        }
        if ($propiedad !== 'texto') {
            $this->armar();
        }
    }

    /**
     * Hoy, 7 días, 30 días: a partir de la fecha de «desde».
     */
    public function rango(int $dias): void
    {
        $this->hasta = $this->fechaInicial()->addDays(max(1, min($dias, AvisoEquipo::DIAS_MAXIMOS)) - 1)->format('Y-m-d');
        $this->armar();
    }

    public function guardarFormato(): void
    {
        $propiedad = $this->propiedad();
        if ($propiedad === null) {
            return;
        }
        FormatoAviso::guardar($propiedad->id, $this->formato);
        $this->armar();
        Notification::make()->success()->title(__('equipo.aviso.formato_guardado'))->send();
    }

    public function restablecerFormato(): void
    {
        $propiedad = $this->propiedad();
        if ($propiedad === null) {
            return;
        }
        FormatoAviso::restablecer($propiedad->id);
        $this->formato = FormatoAviso::porOmision();
        $this->armar();
        Notification::make()->success()->title(__('equipo.aviso.formato_restablecido'))->send();
    }

    /**
     * Cada formato de fecha, escrito con la fecha de hoy: se entiende mejor que su nombre.
     *
     * @return array<string, string>
     */
    public function ejemplosFecha(): array
    {
        $hoy = $this->propiedad()?->hoy() ?? CarbonImmutable::now();
        $ejemplos = [];
        foreach (FormatoAviso::FECHAS as $formato) {
            $ejemplos[$formato] = FormatoAviso::fecha($hoy, $formato);
        }

        return $ejemplos;
    }

    private function fechaInicial(): CarbonImmutable
    {
        $propiedad = $this->propiedad();
        $hoy = $propiedad?->hoy() ?? CarbonImmutable::now();

        return $this->leerFecha($this->desde, $propiedad?->zona_horaria) ?? $hoy;
    }

    private function leerFecha(string $texto, ?string $zona): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $texto, $zona)?->startOfDay();
        } catch (Throwable) {
            return null;
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
        $desde = $this->fechaInicial();
        // Una fecha borrada o al revés no truena: se queda en un rango que se puede leer.
        $hasta = $this->leerFecha($this->hasta, $propiedad->zona_horaria) ?? $desde->addDays(self::DIAS_INICIALES - 1);
        if ($hasta->lt($desde)) {
            $hasta = $desde;
        }
        $this->excedido = $desde->diffInDays($hasta) >= AvisoEquipo::DIAS_MAXIMOS;
        if ($this->excedido) {
            $hasta = $desde->addDays(AvisoEquipo::DIAS_MAXIMOS - 1);
        }
        $this->hasta = $hasta->format('Y-m-d');
        $this->formato = FormatoAviso::limpiar($this->formato);
        $contacto = $this->puedeVerContacto();
        $this->texto = app(AvisoEquipo::class)->generar($propiedad, $desde, (int) $desde->diffInDays($hasta) + 1, [
            'nombre' => $this->nombre,
            'personas' => $this->personas,
            'origen' => $this->origen,
            'noches' => $this->noches,
            'telefono' => $this->telefono && $contacto,
            'comentario' => $this->comentario && $contacto,
            'limpieza' => $this->limpieza,
        ], $this->formato);
    }
}
