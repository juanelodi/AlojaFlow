<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Caja\GestorTurnos;
use App\Dinero\PagoRecibido;
use App\Dinero\SegundaMoneda;
use App\Documentos\GeneradorPdf;
use App\Enums\MetodoPago;
use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Caja;
use App\Models\Pago;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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
                    ->description(fn (Pago $record): ?string => $record->moneda_recibida === null ? null
                        : __('dinero.segunda.recibido', ['importe' => Formato::dinero((int) $record->importe_recibido, $record->moneda_recibida), 'tipo' => Formato::tasa($record->tipo_cambio)]))
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

    private function segunda(): SegundaMoneda
    {
        return new SegundaMoneda($this->reserva()->propiedad);
    }

    private function recibidoEnSegunda(Get $get): bool
    {
        return $this->segunda()->activa() && $get('recibido_en') === $this->segunda()->moneda();
    }

    /**
     * «Son $1,850.00 MXN»: lo que se va a anotar en la reserva.
     */
    private function textoConversion(Get $get): ?string
    {
        $moneda = (string) $this->segunda()->moneda();
        if (! is_numeric($get('importe')) || ! is_numeric($get('tipo_cambio')) || (float) $get('tipo_cambio') <= 0) {
            return null;
        }
        $principal = $this->segunda()->aPrincipal(Dinero::aUnidadMenor((string) $get('importe'), $moneda), (string) $get('tipo_cambio'));

        return __('dinero.segunda.se_anota', ['importe' => Formato::dinero($principal, $this->reserva()->moneda)]);
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
                'recibido_en' => $this->reserva()->moneda,
                'tipo_cambio' => $this->segunda()->tipo(),
                'importe' => $this->reserva()->pendiente > 0 ? Dinero::aDecimal($this->reserva()->pendiente, $this->reserva()->moneda) : null,
                'fecha' => $this->reserva()->propiedad->hoy()->format('Y-m-d'),
                'metodo' => MetodoPago::Efectivo->value,
                'caja_id' => GestorTurnos::cajaPropuesta($this->reserva()->propiedad_id),
            ])
            ->schema([
                // Efectivo en la segunda moneda (D-157): se anota lo recibido y se suma en la de la reserva.
                Select::make('recibido_en')->label(__('dinero.segunda.recibido_en'))->native(false)->live()
                    ->visible(fn (): bool => $this->segunda()->activa())
                    ->options(fn (): array => [$this->reserva()->moneda => $this->reserva()->moneda, (string) $this->segunda()->moneda() => (string) $this->segunda()->moneda()])
                    ->afterStateUpdated(function (?string $state, Set $set): void {
                        $pendiente = max(0, $this->reserva()->pendiente);
                        $enSegunda = $state === $this->segunda()->moneda();
                        $importe = $enSegunda ? (int) $this->segunda()->equivalente($pendiente) : $pendiente;
                        $set('importe', $importe > 0 ? Dinero::aDecimal($importe, (string) $state) : null);
                    }),
                TextInput::make('importe')->label(__('ui.pagos.importe'))->helperText(__('ui.pagos.importe_ayuda'))
                    ->required()->numeric()->step('any')->live(onBlur: true)->prefix(fn (Get $get): string => $this->recibidoEnSegunda($get) ? (string) $this->segunda()->moneda() : $this->reserva()->moneda),
                TextInput::make('tipo_cambio')->label(__('dinero.segunda.tipo_pago'))->numeric()->step('any')->minValue(0.000001)->maxValue(1000000)->live(onBlur: true)
                    ->visible(fn (Get $get): bool => $this->recibidoEnSegunda($get))
                    ->required(fn (Get $get): bool => $this->recibidoEnSegunda($get))
                    ->helperText(fn (Get $get): ?string => $this->textoConversion($get)),
                DatePicker::make('fecha')->label(__('ui.pagos.fecha'))->required(),
                Select::make('metodo')->label(__('ui.pagos.metodo'))->options(MetodoPago::opciones())->required()->native(false),
                Select::make('caja_id')->label(__('ui.cajas.modelo_campo'))->native(false)->visible(fn (): bool => Modulos::activo(Modulo::Caja))
                    ->options(fn (): array => Caja::query()->where('propiedad_id', $this->reserva()->propiedad_id)->where('activa', true)->orderBy('nombre')->pluck('nombre', 'id')->all()),
                TextInput::make('referencia')->label(__('ui.pagos.referencia'))->maxLength(100),
            ])
            ->action(function (array $data): void {
                $reserva = $this->reserva();
                $recibido = null;
                $importe = Dinero::aUnidadMenor((string) $data['importe'], $reserva->moneda);
                if ($this->segunda()->activa() && ($data['recibido_en'] ?? null) === $this->segunda()->moneda()) {
                    $moneda = (string) $this->segunda()->moneda();
                    $recibido = new PagoRecibido($moneda, Dinero::aUnidadMenor((string) $data['importe'], $moneda), (string) $data['tipo_cambio']);
                    $importe = $this->segunda()->aPrincipal($recibido->importe, $recibido->tipoCambio);
                }
                try {
                    app(GestorReservas::class)->registrarPago(
                        $reserva,
                        $importe,
                        MetodoPago::from((string) $data['metodo']),
                        CarbonImmutable::parse((string) $data['fecha']),
                        $data['referencia'] ?? null,
                        filled($data['caja_id'] ?? null) ? (int) $data['caja_id'] : null,
                        $recibido,
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
