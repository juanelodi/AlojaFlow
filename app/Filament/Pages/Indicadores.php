<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Concerns\ConPropiedadSeleccionada;
use App\Filament\Pages\Concerns\ConRangoDeFechas;
use App\Modulos\Modulo;
use App\Seguridad\Acceso;
use App\Servicios\Indicadores as ServicioIndicadores;
use App\Servicios\Operacion;
use App\Support\Csv;
use App\Support\Dinero;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ocupación, ADR, RevPAR e ingresos por origen y por mes (M9).
 */
class Indicadores extends Page
{
    use ConPropiedadSeleccionada;
    use ConRangoDeFechas;

    protected string $view = 'filament.pages.indicadores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = 5;

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
        return __('ui.indicadores.titulo');
    }

    public function getTitle(): string|Htmlable
    {
        return __('ui.indicadores.titulo');
    }

    public function periodo(string $cual): void
    {
        $hoy = Operacion::hoyDe($this->propiedadUnica());
        [$desde, $hasta] = match ($cual) {
            'mes_anterior' => [$hoy->subMonthNoOverflow()->startOfMonth(), $hoy->subMonthNoOverflow()->endOfMonth()],
            'anio' => [$hoy->startOfYear(), $hoy->endOfYear()],
            'proximos_90' => [$hoy, $hoy->addDays(89)],
            default => [$hoy->startOfMonth(), $hoy->endOfMonth()],
        };
        $this->desde = $desde->format('Y-m-d');
        $this->hasta = $hasta->format('Y-m-d');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function datos(): array
    {
        $rango = $this->rango();

        return $rango === null ? [] : ServicioIndicadores::porPropiedad($this->propiedadesElegidas(), $rango[0], $rango[1]);
    }

    public function descargarCsv(): ?StreamedResponse
    {
        $datos = $this->datos();
        if ($datos === []) {
            return null;
        }
        $filas = [array_map(fn (string $c): string => __('ui.indicadores.csv.'.$c), [
            'propiedad', 'moneda', 'unidades', 'disponibles', 'ocupadas', 'ocupacion', 'ingresos', 'adr', 'revpar', 'reservas', 'estancia_media', 'canceladas',
        ])];
        foreach ($datos as $fila) {
            $moneda = $fila['moneda'];
            $importe = fn (?int $v): string => $v === null ? '' : Csv::numero(Dinero::aDecimal($v, $moneda), $moneda);
            $filas[] = [
                $fila['propiedad']->nombre,
                $moneda, $fila['unidades'], $fila['disponibles'], $fila['ocupadas'],
                $fila['ocupacion'] === null ? '' : Csv::numero(number_format($fila['ocupacion'] * 100, 1, '.', ''), $moneda),
                $importe($fila['ingresos']), $importe($fila['adr']), $importe($fila['revpar']),
                $fila['reservas'], $fila['estancia_media'] === null ? '' : Csv::numero((string) $fila['estancia_media'], $moneda), $fila['canceladas'],
            ];
        }

        return Csv::descargaParaExcel($filas, 'indicadores-'.$this->desde.'-'.$this->hasta.'.csv');
    }
}
