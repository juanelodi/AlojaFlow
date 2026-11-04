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
use App\Servicios\Informes as ServicioInformes;
use App\Servicios\Operacion;
use App\Support\Csv;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Todos los informes en una pantalla, con su descarga en CSV (M9).
 */
class Informes extends Page
{
    use ConPropiedadSeleccionada;

    protected string $view = 'filament.pages.informes';

    protected static ?string $slug = 'informes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 5;

    #[Url]
    public string $informe = 'unidades';

    #[Url]
    public string $desde = '';

    #[Url]
    public string $hasta = '';

    protected static function permiso(): string
    {
        return Acceso::INDICADORES;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.operacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('informes.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('informes.titulo');
    }

    public function mount(): void
    {
        $hoy = Operacion::hoyDe($this->propiedadUnica());
        $this->desde = $this->desde !== '' ? $this->desde : $hoy->startOfMonth()->format('Y-m-d');
        $this->hasta = $this->hasta !== '' ? $this->hasta : $hoy->endOfMonth()->format('Y-m-d');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function rango(): ?array
    {
        try {
            $desde = CarbonImmutable::createFromFormat('!Y-m-d', $this->desde);
            $hasta = CarbonImmutable::createFromFormat('!Y-m-d', $this->hasta);
        } catch (Throwable) {
            return null;
        }
        if (! $desde || ! $hasta || $hasta->lt($desde) || $desde->diffInDays($hasta) > 731) {
            return null;
        }

        return [$desde, $hasta];
    }

    /**
     * @return array{columnas: list<string>, filas: list<list<string>>}
     */
    public function datos(): array
    {
        $rango = $this->rango();
        if ($rango === null || ! in_array($this->informe, ServicioInformes::INFORMES, true)) {
            return ['columnas' => [], 'filas' => []];
        }

        return ServicioInformes::generar($this->informe, $this->propiedadesElegidas(), $rango[0], $rango[1]);
    }

    public function descargarCsv(): ?StreamedResponse
    {
        $datos = $this->datos();
        if ($datos['columnas'] === []) {
            return null;
        }

        return response()->streamDownload(function () use ($datos): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $datos['columnas']);
            foreach ($datos['filas'] as $fila) {
                fputcsv($salida, Csv::fila($fila));
            }
            fclose($salida);
        }, $this->informe.'-'.$this->desde.'-'.$this->hasta.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
