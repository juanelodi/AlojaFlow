<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Facturas\Pages;

use App\Filament\Resources\Facturas\FacturaResource;
use App\Models\Factura;
use App\Models\Propiedad;
use App\Paises\Paises;
use App\Support\Csv;
use App\Support\Dinero;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListFacturas extends ListRecords
{
    protected static string $resource = FacturaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label(__('dinero.facturas.exportar'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([
                    DatePicker::make('desde')->label(__('dinero.facturas.desde'))->required()->default(now()->startOfMonth()),
                    DatePicker::make('hasta')->label(__('dinero.facturas.hasta'))->required()->default(now()->endOfMonth())->afterOrEqual('desde'),
                    Select::make('formato')->label(__('herramientas.facturas.formato'))->required()->native(false)->default('excel')
                        ->options(fn (): array => ['excel' => __('herramientas.facturas.excel'), 'csv' => __('herramientas.facturas.csv')] + ($this->hayLibroFiscal() ? ['libro' => __('autoridad.libro.formato')] : [])),
                ])
                ->action(fn (array $data): StreamedResponse => $data['formato'] === 'libro'
                    ? $this->libro((string) $data['desde'], (string) $data['hasta'])
                    : $this->csv((string) $data['desde'], (string) $data['hasta'], (string) $data['formato'])),
        ];
    }

    private function hayLibroFiscal(): bool
    {
        return Propiedad::query()->whereIn('id', FacturaResource::propiedadesGestionables())->whereNotNull('paquete_pais')->get()
            ->contains(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->exportaLibroFiscal());
    }

    /**
     * Una fila por factura y tipo de impuesto, que es como las pide un programa de facturación. No es un registro de VeriFactu: el documento fiscal lo emite ese programa.
     */
    private function libro(string $desde, string $hasta): StreamedResponse
    {
        $propiedades = Propiedad::query()->whereIn('id', FacturaResource::propiedadesGestionables())->whereNotNull('paquete_pais')->get()
            ->filter(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->exportaLibroFiscal())->pluck('id');
        $facturas = Factura::query()->with('lineas.impuestos')->whereIn('propiedad_id', $propiedades)
            ->whereDate('emitida_en', '>=', $desde)->whereDate('emitida_en', '<=', $hasta)->orderBy('emitida_en')->get();
        $importe = fn (int $centavos, string $moneda): string => str_replace('.', ',', Dinero::aDecimal($centavos, $moneda));

        return response()->streamDownload(function () use ($facturas, $importe): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            $campos = ['nif_emisor', 'emisor', 'serie', 'numero', 'fecha', 'tipo', 'nif_receptor', 'receptor', 'impuesto', 'tasa', 'base', 'cuota', 'no_sujeto', 'total', 'estado'];
            fputcsv($salida, array_map(fn (string $c): string => __('autoridad.libro.'.$c), $campos), ';');
            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                $porTipo = [];
                $sinImpuesto = 0;
                foreach ($factura->lineas as $linea) {
                    if ($linea->impuestos->isEmpty()) {
                        $sinImpuesto += $linea->importe;
                    }
                    foreach ($linea->impuestos as $impuesto) {
                        $clave = $impuesto->nombre.'|'.(float) $impuesto->tasa;
                        $porTipo[$clave] ??= ['nombre' => $impuesto->nombre, 'tasa' => (float) $impuesto->tasa, 'base' => 0, 'cuota' => 0];
                        $porTipo[$clave]['base'] += $impuesto->base;
                        $porTipo[$clave]['cuota'] += $impuesto->importe;
                    }
                }
                $nif = (string) ($factura->receptor['identificador_fiscal'] ?? '');
                $comun = [
                    $factura->emisor['identificador_fiscal'] ?? '',
                    $factura->emisor['nombre'] ?? '',
                    $factura->serie,
                    $factura->numero,
                    $factura->emitida_en->format('Y-m-d'),
                    $nif !== '' ? 'F1' : 'F2',
                    $nif,
                    $factura->receptor['nombre'] ?? '',
                ];
                foreach ($porTipo === [] ? [null] : array_values($porTipo) as $i => $fila) {
                    fputcsv($salida, Csv::fila([
                        ...$comun,
                        $fila['nombre'] ?? '',
                        $fila === null ? '' : str_replace('.', ',', (string) $fila['tasa']),
                        $fila === null ? '' : $importe($fila['base'], $factura->moneda),
                        $fila === null ? '' : $importe($fila['cuota'], $factura->moneda),
                        $i === 0 ? $importe($sinImpuesto, $factura->moneda) : '',
                        $i === 0 ? $importe($factura->total, $factura->moneda) : '',
                        $factura->estado->etiqueta(),
                    ]), ';');
                }
            }
            fclose($salida);
        }, 'libro-facturas-'.$desde.'-'.$hasta.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Con marca de orden de bytes para que Excel respete los acentos. El Excel en español separa con punto y coma y espera coma decimal. Pagado y
     * pendiente son los de las reservas de la factura, no los del documento: pueden no coincidir.
     */
    private function csv(string $desde, string $hasta, string $formato = 'excel'): StreamedResponse
    {
        $separador = $formato === 'excel' ? ';' : ',';
        $importe = fn (int $centavos, string $moneda): string => $formato === 'excel'
            ? str_replace('.', ',', Dinero::aDecimal($centavos, $moneda))
            : Dinero::aDecimal($centavos, $moneda);

        $facturas = Factura::query()->with('reservas')->whereIn('propiedad_id', FacturaResource::propiedadesGestionables())
            ->whereDate('emitida_en', '>=', $desde)->whereDate('emitida_en', '<=', $hasta)->orderBy('emitida_en')->get();

        return response()->streamDownload(function () use ($facturas, $separador, $importe): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            $campos = ['serie', 'folio', 'fecha', 'identificador', 'nombre', 'correo', 'codigo_postal', 'llegada', 'salida', 'subtotal', 'impuestos', 'total', 'pagado', 'pendiente', 'moneda', 'estado', 'uuid', 'reservas'];
            fputcsv($salida, array_map(fn (string $c): string => __('dinero.contador.'.$c), $campos), $separador);
            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                fputcsv($salida, Csv::fila([
                    $factura->serie,
                    $factura->numero,
                    $factura->emitida_en->format('Y-m-d H:i:s'),
                    $factura->receptor['identificador_fiscal'] ?? '',
                    $factura->receptor['nombre'] ?? '',
                    $factura->receptor['correo'] ?? '',
                    $factura->receptor['codigo_postal'] ?? '',
                    $factura->reservas->min('llegada')?->format('Y-m-d'),
                    $factura->reservas->max('salida')?->format('Y-m-d'),
                    $importe($factura->subtotal, $factura->moneda),
                    $importe($factura->impuestos, $factura->moneda),
                    $importe($factura->total, $factura->moneda),
                    $importe((int) $factura->reservas->sum('pagado'), $factura->moneda),
                    $importe((int) $factura->reservas->sum('pendiente'), $factura->moneda),
                    $factura->moneda,
                    $factura->estado->etiqueta(),
                    $factura->uuid_fiscal,
                    $factura->reservas->pluck('numero')->implode(' '),
                ]), $separador);
            }
            fclose($salida);
        }, 'facturas-'.$desde.'-'.$hasta.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
