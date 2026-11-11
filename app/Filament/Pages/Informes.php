<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Documentos\GeneradorPdf;
use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Pages\Concerns\ConRangoDeFechas;
use App\Models\Propiedad;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Servicios\Informes as ServicioInformes;
use App\Support\Csv;
use App\Support\Formato;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Todos los informes en una pantalla, con su descarga en CSV (M9).
 */
class Informes extends Page
{
    use ConPropiedadSeleccionada;
    use ConRangoDeFechas;

    protected string $view = 'filament.pages.informes';

    protected static ?string $slug = 'informes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 4;

    #[Url]
    public string $informe = 'unidades';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    protected static function modulo(): ?Modulo
    {
        return Modulo::Informes;
    }

    protected static function permiso(): string
    {
        return Acceso::INDICADORES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getNavigationLabel(): string
    {
        return __('informes.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('informes.titulo');
    }

    /**
     * @return array{columnas: list<string>, filas: list<list<string>>, grafica?: array<string, mixed>|null}
     */
    public function datos(): array
    {
        $rango = $this->rango();
        if ($rango === null || ! in_array($this->informe, ServicioInformes::INFORMES, true)) {
            return ['columnas' => [], 'filas' => []];
        }

        return ServicioInformes::generar($this->informe, $this->propiedadesElegidas(), $rango[0], $rango[1]);
    }

    public function descargarPdf(): ?StreamedResponse
    {
        $datos = $this->datos();
        $rango = $this->rango();
        if ($datos['columnas'] === [] || $rango === null) {
            return null;
        }
        $propiedades = Propiedad::query()->whereIn('id', $this->propiedadesElegidas())->orderBy('nombre')->pluck('nombre')->implode(', ');
        $pdf = app(GeneradorPdf::class)->informe(__('informes.nombres.'.$this->informe), $propiedades, Formato::fecha($rango[0]).' – '.Formato::fecha($rango[1]), $datos['columnas'], $datos['filas']);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $this->informe.'-'.$this->desde.'-'.$this->hasta.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function descargarCsv(): ?StreamedResponse
    {
        $datos = $this->datos();
        if ($datos['columnas'] === []) {
            return null;
        }

        return Csv::descargaParaExcel([$datos['columnas'], ...$datos['filas']], $this->informe.'-'.$this->desde.'-'.$this->hasta.'.csv');
    }
}
