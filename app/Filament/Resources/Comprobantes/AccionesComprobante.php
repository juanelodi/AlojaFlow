<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Comprobantes;

use App\Caja\GestorTurnos;
use App\Comprobantes\GestorComprobantes;
use App\Enums\EstadoComprobante;
use App\Models\Caja;
use App\Models\ComprobantePago;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\ReservaRechazada;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ver, confirmar y rechazar: lo mismo en el grupo, en la reserva y en la lista general.
 */
final class AccionesComprobante
{
    public static function tabla(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['revisor', 'archivo']))
            ->defaultSort('id', 'desc')
            ->columns(self::columnas())
            ->emptyStateHeading(__('grupos.comprobante.vacio'))
            ->recordActions(self::acciones());
    }

    /**
     * @return list<TextColumn>
     */
    public static function columnas(): array
    {
        return [
            TextColumn::make('fecha')->label(__('ui.pagos.fecha'))->formatStateUsing(fn (ComprobantePago $record): string => Formato::fecha($record->fecha)),
            TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()->formatStateUsing(fn (ComprobantePago $record): string => Formato::dinero($record->importe, $record->moneda)),
            TextColumn::make('referencia')->label(__('ui.pagos.referencia'))->placeholder('—'),
            TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                ->formatStateUsing(fn (ComprobantePago $record): string => $record->estado->etiqueta())
                ->color(fn (ComprobantePago $record): string => match ($record->estado) {
                    EstadoComprobante::PorConfirmar => 'warning',
                    EstadoComprobante::Confirmado => 'success',
                    EstadoComprobante::Rechazado => 'danger',
                }),
            TextColumn::make('motivo_rechazo')->label(__('grupos.comprobante.motivo'))->placeholder('—')->wrap()->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('revisor.name')->label(__('grupos.comprobante.reviso'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return list<Action>
     */
    public static function acciones(): array
    {
        return [
            Action::make('verComprobante')
                ->label(__('grupos.comprobante.ver'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->visible(fn (ComprobantePago $record): bool => $record->archivo_id !== null)
                ->url(fn (ComprobantePago $record): string => route('archivos.ver', (int) $record->archivo_id), shouldOpenInNewTab: true),
            Action::make('confirmarComprobante')
                ->label(__('grupos.comprobante.confirmar'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (ComprobantePago $record): bool => $record->estado === EstadoComprobante::PorConfirmar)
                ->modalDescription(fn (ComprobantePago $record): string => __('grupos.comprobante.confirmar_ayuda', ['importe' => Formato::dinero($record->importe, $record->moneda)]))
                ->fillForm(fn (ComprobantePago $record): array => ['caja_id' => GestorTurnos::cajaPropuesta($record->propiedad_id)])
                ->schema(fn (ComprobantePago $record): array => [
                    Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)->visible(fn (): bool => Modulos::activo(Modulo::Caja))
                        ->options(fn (): array => Caja::query()->where('propiedad_id', $record->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                ])
                ->action(function (ComprobantePago $record, array $data, GestorComprobantes $comprobantes): void {
                    $cajaId = filled($data['caja_id'] ?? null) ? Caja::query()->where('propiedad_id', $record->propiedad_id)->whereKey((int) $data['caja_id'])->value('id') : null;
                    try {
                        $comprobantes->confirmar($record, $cajaId);
                        Notification::make()->success()->title(__('grupos.comprobante.confirmado'))->send();
                    } catch (ReservaRechazada $e) {
                        Notification::make()->danger()->persistent()->title($e->getMessage())->send();
                    }
                }),
            Action::make('rechazarComprobante')
                ->label(__('grupos.comprobante.rechazar'))
                ->icon(Heroicon::OutlinedXCircle)
                ->color('gray')
                ->visible(fn (ComprobantePago $record): bool => $record->estado === EstadoComprobante::PorConfirmar)
                ->schema([TextInput::make('motivo')->label(__('grupos.comprobante.motivo'))->helperText(__('grupos.comprobante.motivo_ayuda'))->required()->maxLength(255)])
                ->action(function (ComprobantePago $record, array $data, GestorComprobantes $comprobantes): void {
                    $comprobantes->rechazar($record, (string) $data['motivo']);
                    Notification::make()->success()->title(__('grupos.comprobante.rechazado'))->send();
                }),
        ];
    }
}
