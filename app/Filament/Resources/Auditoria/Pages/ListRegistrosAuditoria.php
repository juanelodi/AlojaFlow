<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Auditoria\Pages;

use App\Filament\Resources\Auditoria\RegistroAuditoriaResource;
use App\Models\RegistroAuditoria;
use App\Support\Ajustes;
use App\Support\Csv;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListRegistrosAuditoria extends ListRecords
{
    protected static string $resource = RegistroAuditoriaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportar')
                ->label(__('permisos.auditoria.exportar'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([
                    DatePicker::make('desde')->label(__('ui.indicadores.desde'))->required()->default(fn (): string => now()->subDays(30)->format('Y-m-d')),
                    DatePicker::make('hasta')->label(__('ui.indicadores.hasta'))->required()->default(fn (): string => now()->format('Y-m-d')),
                ])
                ->action(fn (array $data): StreamedResponse => $this->csv((string) $data['desde'], (string) $data['hasta'])),
            Action::make('plazo')
                ->label(__('permisos.auditoria.plazo'))
                ->icon(Heroicon::OutlinedClock)
                ->color('gray')
                ->fillForm(fn (): array => ['dias' => RegistroAuditoria::diasConservar()])
                ->schema([TextInput::make('dias')->label(__('permisos.auditoria.dias'))->helperText(__('permisos.auditoria.dias_ayuda'))->numeric()->required()->minValue(30)->maxValue(3650)])
                ->action(function (array $data): void {
                    Ajustes::guardar(RegistroAuditoria::AJUSTE_DIAS, (int) $data['dias']);
                    RegistroAuditoria::anotar('auditoria.plazo', null, ['dias' => (int) $data['dias']]);
                    Notification::make()->success()->title(__('permisos.auditoria.plazo_guardado'))->send();
                }),
        ];
    }

    private function csv(string $desde, string $hasta): StreamedResponse
    {
        RegistroAuditoria::anotar('auditoria.exportar', null, ['desde' => $desde, 'hasta' => $hasta]);

        return response()->streamDownload(function () use ($desde, $hasta): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, Csv::fila([__('ui.auditoria.cuando'), __('ui.auditoria.quien'), __('ui.auditoria.accion'), __('ui.auditoria.objeto'), __('ui.auditoria.detalle'), 'IP']));
            RegistroAuditoria::query()->with('usuario')->whereDate('creado_en', '>=', $desde)->whereDate('creado_en', '<=', $hasta)->orderBy('id')
                ->chunk(500, function ($filas) use ($salida): void {
                    foreach ($filas as $fila) {
                        fputcsv($salida, Csv::fila([
                            Formato::momento($fila->creado_en),
                            $fila->usuario->name ?? __('ui.auditoria.sistema'),
                            $fila->accion,
                            $fila->modelo_tipo === null ? '' : $fila->modelo_tipo.' #'.$fila->modelo_id,
                            $fila->cambios === null ? '' : (string) json_encode($fila->cambios, JSON_UNESCAPED_UNICODE),
                            (string) $fila->ip,
                        ]));
                    }
                });
            fclose($salida);
        }, 'auditoria-'.$desde.'-'.$hasta.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
