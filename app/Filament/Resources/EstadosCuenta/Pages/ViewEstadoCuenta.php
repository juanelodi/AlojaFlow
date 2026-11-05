<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\EstadosCuenta\Pages;

use App\Documentos\GeneradorPdf;
use App\Enums\FaseEstadoCuenta;
use App\Filament\Resources\EstadosCuenta\EstadoCuentaResource;
use App\Models\EstadoCuenta;
use App\Propietarios\GestorEstados;
use App\Support\Csv;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewEstadoCuenta extends ViewRecord
{
    protected static string $resource = EstadoCuentaResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalcular')
                ->label(__('propietarios.estado.recalcular'))
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (EstadoCuenta $record): bool => $record->fase === FaseEstadoCuenta::Borrador)
                ->action(function (EstadoCuenta $record, GestorEstados $estados): void {
                    $estados->generar($record->propietario, $record->desde);
                    $this->refrescar();
                    Notification::make()->success()->title(__('propietarios.estado.recalculado'))->send();
                }),
            Action::make('enviar')
                ->label(__('propietarios.estado.enviar'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn (EstadoCuenta $record): bool => $record->fase === FaseEstadoCuenta::Borrador)
                ->requiresConfirmation()
                ->modalDescription(fn (EstadoCuenta $record): string => filled($record->propietario->correo) ? __('propietarios.estado.enviar_ayuda', ['correo' => $record->propietario->correo]) : __('propietarios.estado.enviar_sin_correo'))
                ->action(function (EstadoCuenta $record, GestorEstados $estados): void {
                    $estados->enviar($record);
                    $this->refrescar();
                    Notification::make()->success()->title(__('propietarios.estado.enviado'))->send();
                }),
            Action::make('pagar')
                ->label(__('propietarios.estado.pagar'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->visible(fn (EstadoCuenta $record): bool => $record->fase === FaseEstadoCuenta::Enviado)
                ->modalDescription(__('propietarios.estado.pagar_ayuda'))
                ->fillForm(fn (EstadoCuenta $record): array => ['fecha' => $record->propiedad->hoy()->format('Y-m-d')])
                ->schema([
                    DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required(),
                    TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
                ])
                ->action(function (EstadoCuenta $record, array $data, GestorEstados $estados): void {
                    $estados->pagar($record, CarbonImmutable::parse((string) $data['fecha']), $data['referencia'] ?? null);
                    $this->refrescar();
                    Notification::make()->success()->title(__('propietarios.estado.pagado'))->send();
                }),
            Action::make('reabrir')
                ->label(__('propietarios.estado.reabrir'))
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('gray')
                ->visible(fn (EstadoCuenta $record): bool => $record->fase !== FaseEstadoCuenta::Borrador)
                ->requiresConfirmation()
                ->modalDescription(__('propietarios.estado.reabrir_ayuda'))
                ->action(function (EstadoCuenta $record, GestorEstados $estados): void {
                    $estados->reabrir($record);
                    $this->refrescar();
                    Notification::make()->success()->title(__('propietarios.estado.reabierto'))->send();
                }),
            Action::make('pdf')
                ->label(__('propietarios.estado.descargar_pdf'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(function (EstadoCuenta $record, GeneradorPdf $generador): StreamedResponse {
                    $pdf = $generador->estadoCuenta($record);

                    return response()->streamDownload(function () use ($pdf): void {
                        echo $pdf;
                    }, 'estado-'.$record->desde->format('Y-m').'.pdf', ['Content-Type' => 'application/pdf']);
                }),
            Action::make('csv')
                ->label(__('propietarios.estado.descargar_csv'))
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->action(fn (EstadoCuenta $record): StreamedResponse => response()->streamDownload(function () use ($record): void {
                    $salida = fopen('php://output', 'wb');
                    if ($salida === false) {
                        return;
                    }
                    foreach (self::filasCsv($record) as $fila) {
                        fputcsv($salida, Csv::fila($fila));
                    }
                    fclose($salida);
                }, 'estado-'.$record->desde->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8'])),
        ];
    }

    /**
     * @return list<list<string|int|float|null>>
     */
    private static function filasCsv(EstadoCuenta $estado): array
    {
        $decimal = fn (int $importe): string => Dinero::aDecimal($importe, $estado->moneda);
        $filas = [[__('informes.columnas.reserva'), __('ui.comun.unidad'), __('ui.comun.llegada'), __('ui.comun.salida'), __('ui.comun.noches'), __('propietarios.estado.ingresos'), __('propietarios.estado.portal'), __('propietarios.estado.comision')]];
        foreach ((array) ($estado->detalle['reservas'] ?? []) as $linea) {
            $filas[] = [$linea['numero'], $linea['unidad'], $linea['llegada'], $linea['salida'], $linea['noches'], $decimal($linea['ingreso']), $decimal($linea['portal']), $decimal($linea['comision'])];
        }
        foreach ((array) ($estado->detalle['gastos'] ?? []) as $linea) {
            $filas[] = [__('propietarios.estado.gastos'), $linea['unidad'], $linea['fecha'], '', '', '-'.$decimal($linea['importe']), '', $linea['concepto']];
        }
        $filas[] = [__('propietarios.estado.cargo_fijo'), '', '', '', '', '-'.$decimal($estado->cargo_fijo), '', ''];
        $filas[] = [__('propietarios.estado.neto'), '', '', '', '', $decimal($estado->neto), '', ''];

        return $filas;
    }

    /**
     * Lo que se ve tiene que ser lo que acaba de quedar.
     */
    private function refrescar(): void
    {
        /** @var EstadoCuenta $estado */
        $estado = $this->getRecord();
        $estado->refresh();
    }
}
