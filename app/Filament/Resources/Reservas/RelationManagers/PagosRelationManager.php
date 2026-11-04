<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Documentos\GeneradorPdf;
use App\Enums\MetodoPago;
use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Cobros y devoluciones de una reserva propia. Los recalcula GestorReservas: reservas.pagado es
 * siempre la suma de esta lista.
 */
class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Reserva && ! $ownerRecord->sincronizada && $ownerRecord->tipo === TipoReserva::Reserva;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ui.pagos.titulo');
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('fecha')->label(__('ui.pagos.fecha'))
                    ->formatStateUsing(fn (Pago $record): string => Formato::fecha($record->fecha)),
                TextColumn::make('metodo')->label(__('ui.pagos.metodo'))
                    ->formatStateUsing(fn (Pago $record): string => $record->metodo->etiqueta()),
                TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()
                    ->formatStateUsing(fn (Pago $record): string => Formato::dinero($record->importe, $record->moneda))
                    ->color(fn (Pago $record): ?string => $record->importe < 0 ? 'danger' : null),
                TextColumn::make('referencia')->label(__('ui.pagos.referencia'))->placeholder('—'),
                TextColumn::make('usuario.name')->label(__('ui.pagos.registro'))->placeholder('—')->toggleable(),
            ])
            ->emptyStateHeading(__('ui.pagos.vacio'))
            ->headerActions([$this->accionRegistrar()])
            ->recordActions([$this->accionRecibo(), $this->accionQuitar()]);
    }

    private function reserva(): Reserva
    {
        /** @var Reserva $reserva */
        $reserva = $this->getOwnerRecord();

        return $reserva;
    }

    private function puedeGestionar(): bool
    {
        return in_array($this->reserva()->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }

    private function accionRegistrar(): Action
    {
        return Action::make('registrarPago')
            ->label(__('ui.pagos.registrar'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->visible(fn (): bool => $this->puedeGestionar())
            ->fillForm(fn (): array => [
                'importe' => $this->reserva()->pendiente > 0 ? Dinero::aDecimal($this->reserva()->pendiente, $this->reserva()->moneda) : null,
                'fecha' => $this->reserva()->propiedad->hoy()->format('Y-m-d'),
                'metodo' => MetodoPago::Efectivo->value,
            ])
            ->schema([
                TextInput::make('importe')->label(__('ui.pagos.importe'))->helperText(__('ui.pagos.importe_ayuda'))
                    ->required()->numeric()->step('any')->prefix(fn (): string => $this->reserva()->moneda),
                DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required(),
                Select::make('metodo')->label(__('ui.pagos.metodo'))->options(MetodoPago::opciones())->required()->native(false),
                Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)
                    ->options(fn (): array => Caja::query()->where('propiedad_id', $this->reserva()->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
            ])
            ->action(function (array $data): void {
                $reserva = $this->reserva();
                try {
                    app(GestorReservas::class)->registrarPago(
                        $reserva,
                        Dinero::aUnidadMenor((string) $data['importe'], $reserva->moneda),
                        MetodoPago::from((string) $data['metodo']),
                        CarbonImmutable::parse((string) $data['fecha']),
                        $data['referencia'] ?? null,
                        filled($data['caja_id'] ?? null) ? (int) $data['caja_id'] : null,
                    );
                } catch (ReservaRechazada $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }
                Notification::make()->success()->title(__('ui.pagos.registrado'))->send();
                $this->redirect(ReservaResource::getUrl('view', ['record' => $reserva]));
            });
    }

    private function accionRecibo(): Action
    {
        return Action::make('reciboPdf')
            ->label(__('documentos.descargar_recibo'))
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->action(function (Pago $record): StreamedResponse {
                abort_unless($record->reserva_id === $this->reserva()->id, 404);

                return response()->streamDownload(function () use ($record): void {
                    echo app(GeneradorPdf::class)->recibo($record);
                }, 'recibo-'.$record->id.'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    private function accionQuitar(): Action
    {
        return Action::make('quitarPago')
            ->label(__('ui.pagos.quitar'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->puedeGestionar())
            ->action(function (Pago $record): void {
                $reserva = $this->reserva();
                abort_unless($record->reserva_id === $reserva->id, 404);
                app(GestorReservas::class)->quitarPago($record);
                Notification::make()->success()->title(__('ui.pagos.quitado'))->send();
                $this->redirect(ReservaResource::getUrl('view', ['record' => $reserva]));
            });
    }
}
