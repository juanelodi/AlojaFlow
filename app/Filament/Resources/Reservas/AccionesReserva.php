<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas;

use App\Documentos\GeneradorPdf;
use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cancelar, reactivar y registrar la entrada y la salida de una reserva propia.
 */
final class AccionesReserva
{
    public static function entrada(): Action
    {
        return Action::make('registrarEntrada')
            ->label(__('ui.reservas.registrar_entrada'))
            ->icon(Heroicon::OutlinedArrowRightEndOnRectangle)
            ->color('success')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->tipo === TipoReserva::Reserva
                && $record->estado !== EstadoReserva::Cancelada && $record->entrada_registrada_en === null)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->registrarEntrada($record), __('ui.reservas.entrada_ok')));
    }

    public static function confirmar(): Action
    {
        return Action::make('confirmar')
            ->label(__('ui.reservas.confirmar'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado === EstadoReserva::SinConfirmar)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->confirmar($record), __('ui.reservas.confirmada_ok')));
    }

    public static function salida(): Action
    {
        return Action::make('registrarSalida')
            ->label(__('ui.reservas.registrar_salida'))
            ->icon(Heroicon::OutlinedArrowLeftStartOnRectangle)
            ->color('info')
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estaEnCasa())
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->registrarSalida($record), __('ui.reservas.salida_ok')));
    }

    public static function confirmacionPdf(): Action
    {
        return Action::make('confirmacionPdf')
            ->label(__('documentos.descargar_confirmacion'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (Reserva $record): bool => $record->tipo === TipoReserva::Reserva)
            ->action(fn (Reserva $record): StreamedResponse => response()->streamDownload(function () use ($record): void {
                echo app(GeneradorPdf::class)->confirmacion($record);
            }, 'reserva-'.str_replace('/', '-', $record->numero).'.pdf', ['Content-Type' => 'application/pdf']));
    }

    public static function deshacerRegistro(): Action
    {
        return Action::make('deshacerRegistro')
            ->label(fn (Reserva $record): string => $record->salida_registrada_en !== null ? __('ui.reservas.deshacer_salida') : __('ui.reservas.deshacer_entrada'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->entrada_registrada_en !== null)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->deshacerRegistro($record), __('ui.reservas.deshecho_ok')));
    }

    public static function cancelar(): Action
    {
        return Action::make('cancelar')
            ->label(__('ui.reservas.cancelar'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('ui.reservas.cancelar_ayuda'))
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado !== EstadoReserva::Cancelada && ! $record->estaEnCasa())
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->cancelar($record), __('ui.reservas.cancelada_ok')));
    }

    public static function reactivar(): Action
    {
        return Action::make('reactivar')
            ->label(__('ui.reservas.reactivar'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (Reserva $record): bool => self::gestionable($record) && $record->estado === EstadoReserva::Cancelada)
            ->action(fn (Reserva $record) => self::ejecutar($record, fn (GestorReservas $g) => $g->reactivar($record), __('ui.reservas.reactivada_ok')));
    }

    private static function gestionable(Reserva $reserva): bool
    {
        return ! $reserva->sincronizada && in_array($reserva->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }

    /**
     * @param  callable(GestorReservas): mixed  $accion
     */
    private static function ejecutar(Reserva $reserva, callable $accion, string $mensaje): void
    {
        try {
            $accion(app(GestorReservas::class));
            Notification::make()->success()->title($mensaje)->send();
        } catch (ReservaRechazada $e) {
            Notification::make()->danger()->title($e->getMessage())->persistent()->send();
        }
        // La pantalla sigue usando esta misma instancia: se recarga para que muestre el cambio.
        $reserva->refresh();
    }
}
