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
use App\Support\Csv;
use App\Support\Dinero;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
                ])
                ->action(fn (array $data): StreamedResponse => $this->csv((string) $data['desde'], (string) $data['hasta'])),
        ];
    }

    /**
     * Para abrir en Excel: con marca de orden de bytes para que respete los acentos.
     */
    private function csv(string $desde, string $hasta): StreamedResponse
    {
        $facturas = Factura::query()->with('reservas')->whereIn('propiedad_id', FacturaResource::propiedadesGestionables())
            ->whereDate('emitida_en', '>=', $desde)->whereDate('emitida_en', '<=', $hasta)->orderBy('emitida_en')->get();

        return response()->streamDownload(function () use ($facturas): void {
            $salida = fopen('php://output', 'w');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            $campos = ['serie', 'folio', 'fecha', 'identificador', 'nombre', 'subtotal', 'impuestos', 'total', 'moneda', 'estado', 'uuid', 'reservas'];
            fputcsv($salida, array_map(fn (string $c): string => __('dinero.contador.'.$c), $campos));
            foreach ($facturas as $factura) {
                /** @var Factura $factura */
                fputcsv($salida, Csv::fila([
                    $factura->serie,
                    $factura->numero,
                    $factura->emitida_en->format('Y-m-d H:i:s'),
                    $factura->receptor['identificador_fiscal'] ?? '',
                    $factura->receptor['nombre'] ?? '',
                    Dinero::aDecimal($factura->subtotal, $factura->moneda),
                    Dinero::aDecimal($factura->impuestos, $factura->moneda),
                    Dinero::aDecimal($factura->total, $factura->moneda),
                    $factura->moneda,
                    $factura->estado->etiqueta(),
                    $factura->uuid_fiscal,
                    $factura->reservas->pluck('numero')->implode(' '),
                ]));
            }
            fclose($salida);
        }, 'facturas-'.$desde.'-'.$hasta.'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
