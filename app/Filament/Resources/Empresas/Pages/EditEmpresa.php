<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Empresas\Pages;

use App\Enums\EstadoReserva;
use App\Filament\Resources\Base\EditaEnPropiedad;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Models\Empresa;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use App\Seguridad\Limites;
use App\Support\Csv;
use App\Support\Dinero;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditEmpresa extends EditRecord
{
    use EditaEnPropiedad;

    protected static string $resource = EmpresaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('estadoCuenta')
                ->label(__('caja.empresas.estado_cuenta'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->action(fn (): StreamedResponse => $this->estadoDeCuenta()),
            DeleteAction::make(),
        ];
    }

    /**
     * Lo que ha reservado y lo que debe, para mandárselo.
     */
    private function estadoDeCuenta(): StreamedResponse
    {
        /** @var Empresa $empresa */
        $empresa = $this->record;
        RegistroAuditoria::anotar('empresa.estado_cuenta', $empresa, [], $empresa->propiedad_id);

        return response()->streamDownload(function () use ($empresa): void {
            $salida = fopen('php://output', 'wb');
            if ($salida === false) {
                return;
            }
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, Csv::fila([__('informes.columnas.reserva'), __('ui.comun.huesped'), __('ui.comun.llegada'), __('ui.comun.salida'), __('ui.comun.total'), __('ui.comun.pagado'), __('ui.comun.pendiente')]));
            Limites::reservasVisibles(Reserva::query()->where('empresa_id', $empresa->id), Acceso::usuarioActual())->with('titular')->where('estado', '!=', EstadoReserva::Cancelada->value)->orderBy('llegada')
                ->chunk(500, function ($reservas) use ($salida): void {
                    /** @var Reserva $reserva */
                    foreach ($reservas as $reserva) {
                        fputcsv($salida, Csv::fila([
                            (string) $reserva->numero,
                            (string) $reserva->titular?->nombreCompleto(),
                            Formato::fecha($reserva->llegada),
                            Formato::fecha($reserva->salida),
                            Dinero::formatear($reserva->total, $reserva->moneda),
                            Dinero::formatear($reserva->pagado, $reserva->moneda),
                            Dinero::formatear($reserva->pendiente, $reserva->moneda),
                        ]));
                    }
                });
            fclose($salida);
        }, 'estado-de-cuenta-'.$empresa->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
