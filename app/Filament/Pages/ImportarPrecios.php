<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Resources\Tarifas\TarifaResource;
use App\Models\Propiedad;
use App\Reservas\ImportadorPrecios;
use App\Support\Csv;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subir o pegar precios (D-083): primero se revisa y luego se importa. El archivo lo lee el navegador y llega como texto, así no pasa por el
 * disco del servidor.
 */
class ImportarPrecios extends Page
{
    protected string $view = 'filament.pages.importar-precios';

    protected static ?string $slug = 'importar-precios';

    protected static bool $shouldRegisterNavigation = false;

    public ?int $propiedadId = null;

    public string $texto = '';

    /** @var list<array{linea: int, tarifa: string, tarifa_id: int|null, desde: string, hasta: string, precio: int|null, persona: int|null, error: string|null}> */
    public array $filas = [];

    public static function canAccess(): bool
    {
        return TarifaResource::canViewAny();
    }

    public function getTitle(): string|Htmlable
    {
        return __('operacion.precios.titulo');
    }

    public function mount(): void
    {
        $propiedades = TarifaResource::propiedadesGestionables();
        $this->propiedadId = count($propiedades) === 1 ? $propiedades[0] : null;
    }

    /**
     * @return array<int, string>
     */
    public function propiedades(): array
    {
        return Propiedad::query()->whereIn('id', TarifaResource::propiedadesGestionables())->orderBy('nombre')->pluck('nombre', 'id')->all();
    }

    public function propiedadElegida(): ?Propiedad
    {
        return $this->propiedadId !== null && in_array($this->propiedadId, TarifaResource::propiedadesGestionables(), true) ? Propiedad::query()->find($this->propiedadId) : null;
    }

    public function updatedTexto(): void
    {
        $this->filas = [];
    }

    public function updatedPropiedadId(): void
    {
        $this->filas = [];
    }

    public function revisar(): void
    {
        $propiedad = $this->propiedadElegida();
        $this->filas = $propiedad === null ? [] : app(ImportadorPrecios::class)->revisar($propiedad, $this->texto);
    }

    public function importar(): void
    {
        $propiedad = $this->propiedadElegida();
        if ($propiedad === null) {
            return;
        }
        // Se vuelve a revisar: lo que viene del navegador no se da por bueno.
        $importador = app(ImportadorPrecios::class);
        $importadas = $importador->importar($propiedad, $importador->revisar($propiedad, $this->texto));
        $this->filas = [];
        $this->texto = '';
        Notification::make()->success()->title(trans_choice('operacion.precios.importadas', $importadas, ['n' => $importadas]))->send();
    }

    public function descargarPlantilla(): ?StreamedResponse
    {
        $propiedad = $this->propiedadElegida();
        if ($propiedad === null) {
            return null;
        }

        return Csv::descargaParaExcel(app(ImportadorPrecios::class)->plantilla($propiedad), 'precios-'.$propiedad->codigo.'.csv');
    }
}
