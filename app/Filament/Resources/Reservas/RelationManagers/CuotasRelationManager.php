<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\RelationManagers;

use App\Comunicacion\Mensajero;
use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Facturacion\GestorFacturas;
use App\Filament\Resources\Reservas\AccionesReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\CuotaReserva;
use App\Models\Factura;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\Pasarelas;
use App\Pagos\GestorEnlacesPago;
use App\Reservas\Cotizacion;
use App\Reservas\Mensualidades;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Support\Dinero;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * El calendario de cobros de una estancia larga (D-108): generarlo, ajustarlo a mano, recordar y facturar cada pago.
 */
class CuotasRelationManager extends RelationManager
{
    protected static string $relationship = 'cuotas';

    /** @var array<int, array{cuota: CuotaReserva, pagado: int, estado: string}>|null */
    private ?array $filas = null;

    private ?bool $conPasarelas = null;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('estancias.cuotas.titulo');
    }

    /**
     * Quien cobra y quien factura. En una subordinada los pagos los lleva la principal, y un grupo tiene su propio calendario.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Modulos::activo(Modulo::EstanciasLargas) && $ownerRecord instanceof Reserva && $ownerRecord->tipo === TipoReserva::Reserva && $ownerRecord->grupo_id === null && ! $ownerRecord->sincronizada
            && (in_array($ownerRecord->propiedad_id, ReservaResource::propiedadesGestionables(), true) || Acceso::puede(Acceso::usuarioActual(), Acceso::FACTURACION, $ownerRecord->propiedad_id));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('facturaVigente'))
            ->paginated(false)
            ->description(fn (): ?string => $this->avisoDeSuma())
            ->columns([
                TextColumn::make('periodo')->label(__('estancias.publico.periodo'))->wrap()->state(fn (CuotaReserva $record): string => $record->periodo()),
                TextColumn::make('vence_en')->label(__('estancias.cuotas.vence'))->formatStateUsing(fn (CuotaReserva $record): string => Formato::fecha($record->vence_en)),
                TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()->formatStateUsing(fn (CuotaReserva $record): string => Formato::dinero($record->importe, $this->reserva()->moneda)),
                TextColumn::make('pagado')->label(__('ui.comun.pagado'))->alignEnd()->state(fn (CuotaReserva $record): string => Formato::dinero($this->filaDe($record)['pagado'] ?? 0, $this->reserva()->moneda)),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->state(fn (CuotaReserva $record): string => __('estancias.cuotas.estados.'.($this->filaDe($record)['estado'] ?? 'pendiente')))
                    ->color(fn (CuotaReserva $record): string => match ($this->filaDe($record)['estado'] ?? 'pendiente') {
                        'pagada' => 'success',
                        'atrasada' => 'danger',
                        'parcial' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('factura')->label(__('estancias.cuotas.factura'))->placeholder('—')
                    ->visible(fn (): bool => Modulos::activo(Modulo::Facturacion))
                    ->state(fn (CuotaReserva $record): ?string => $record->facturaVigente?->folio())
                    ->url(fn (CuotaReserva $record): ?string => $record->facturaVigente?->pdf_archivo_id === null ? null : route('archivos.ver', (int) $record->facturaVigente->pdf_archivo_id), shouldOpenInNewTab: true),
            ])
            ->emptyStateHeading(__('estancias.cuotas.vacio'))
            ->emptyStateDescription(__('estancias.cuotas.vacio_ayuda'))
            ->headerActions([
                Action::make('generarCalendario')
                    ->label(__('estancias.cuotas.generar'))
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn (): bool => $this->puedeCambiar())
                    ->modalDescription(__('estancias.cuotas.generar_ayuda'))
                    ->fillForm(fn (): array => [
                        'periodo' => $this->reserva()->llegada->diffInDays($this->reserva()->salida) >= Cotizacion::NOCHES_MES ? Mensualidades::MES : Mensualidades::SEMANA,
                        'primer_vencimiento' => $this->reserva()->llegada->format('Y-m-d'),
                    ])
                    ->schema([
                        Select::make('periodo')->label(__('estancias.cuotas.cada_cuanto'))->native(false)->required()
                            ->options([Mensualidades::MES => __('estancias.cuotas.cada_mes'), Mensualidades::SEMANA => __('estancias.cuotas.cada_semana')]),
                        DatePicker::make('primer_vencimiento')->label(__('estancias.cuotas.primer_vencimiento'))->required(),
                    ])
                    ->action(function (array $data, Mensualidades $mensualidades): void {
                        try {
                            $creadas = $mensualidades->generar($this->reserva(), (string) $data['periodo'], CarbonImmutable::parse((string) $data['primer_vencimiento']));
                            Notification::make()->success()->title(trans_choice('estancias.cuotas.generadas', $creadas))->send();
                        } catch (ReservaRechazada $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                        $this->filas = null;
                    }),
                Action::make('agregarCuota')
                    ->label(__('estancias.cuotas.agregar'))
                    ->icon(Heroicon::OutlinedPlus)
                    ->color('gray')
                    ->visible(fn (): bool => $this->puedeCambiar())
                    ->schema(fn (): array => $this->campos(true))
                    ->action(function (array $data): void {
                        $cuota = $this->reserva()->cuotas()->create($this->datos($data));
                        RegistroAuditoria::anotar('cuota_reserva.crear', $cuota, ['importe' => $cuota->importe], $this->reserva()->propiedad_id);
                        $this->filas = null;
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('facturarCuota')
                        ->label(__('dinero.facturas.facturar'))
                        ->icon(Heroicon::OutlinedDocumentCurrencyDollar)
                        ->color('gray')
                        ->visible(fn (CuotaReserva $record): bool => Modulos::activo(Modulo::Facturacion) && $record->facturaVigente === null && $this->reserva()->estado !== EstadoReserva::Cancelada && Acceso::puede(Acceso::usuarioActual(), Acceso::FACTURACION, $this->reserva()->propiedad_id))
                        ->fillForm(fn (): array => AccionesReserva::datosFactura($this->reserva()))
                        ->schema(fn (): array => AccionesReserva::camposFactura($this->reserva()))
                        ->action(function (CuotaReserva $record, array $data, GestorFacturas $facturas): void {
                            $serie = SerieFacturacion::query()->with('entidad')->findOrFail((int) $data['serie_id']);
                            AccionesReserva::emitirFactura($data, fn (array $receptor): Factura => $facturas->emitirCuota($record, $serie, $receptor, $data['forma_pago'] ?? null, $data['metodo_pago'] ?? null));
                        }),
                    Action::make('enlaceCuota')
                        ->label(__('operacion.enlaces.crear'))
                        ->icon(Heroicon::OutlinedLink)
                        ->color('gray')
                        ->visible(fn (CuotaReserva $record): bool => $this->puedeCambiar() && $this->falta($record) > 0 && $this->cobraEnLinea())
                        ->fillForm(fn (): array => ['dias' => 3])
                        ->schema([TextInput::make('dias')->label(__('operacion.enlaces.dias'))->required()->integer()->minValue(1)->maxValue(60)])
                        ->action(function (CuotaReserva $record, array $data, GestorEnlacesPago $enlaces): void {
                            $reserva = $this->reserva();
                            $enlace = $enlaces->crear($reserva, $this->falta($record), $record->periodo($reserva->propiedad->idioma), (int) $data['dias'], null);
                            Notification::make()->success()->persistent()->title(__('operacion.enlaces.creado'))->body((string) $enlaces->url($enlace, Mensajero::idiomaDe($reserva)))->send();
                        }),
                    Action::make('recordarCuota')
                        ->label(__('operacion.mensajes.whatsapp'))
                        ->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)
                        ->color('gray')
                        ->visible(fn (CuotaReserva $record): bool => $this->falta($record) > 0 && $this->reserva()->estado !== EstadoReserva::Cancelada)
                        ->url(fn (CuotaReserva $record): string => VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($this->reserva()), Mensualidades::recordatorio($this->reserva(), $record, $this->falta($record)), $this->reserva()->propiedad->pais), shouldOpenInNewTab: true),
                    Action::make('editarCuota')
                        ->label(__('estancias.cuotas.editar'))
                        ->icon(Heroicon::OutlinedPencilSquare)
                        ->visible(fn (CuotaReserva $record): bool => $this->puedeCambiar() && $record->facturaVigente === null)
                        ->fillForm(fn (CuotaReserva $record): array => ['concepto' => $record->concepto, 'importe' => Dinero::aDecimal($record->importe, $this->reserva()->moneda), 'vence_en' => $record->vence_en->format('Y-m-d')])
                        ->schema(fn (CuotaReserva $record): array => $this->campos($record->desde === null))
                        ->action(function (CuotaReserva $record, array $data): void {
                            // Se vuelve a mirar aquí: entre abrir el formulario y guardarlo, alguien pudo haberla facturado.
                            if ($record->facturaVigente()->exists()) {
                                return;
                            }
                            $record->update($this->datos($data));
                            RegistroAuditoria::anotar('cuota_reserva.editar', $record, ['importe' => $record->importe], $this->reserva()->propiedad_id);
                            $this->filas = null;
                        }),
                    Action::make('quitarCuota')
                        ->label(__('estancias.cuotas.quitar'))
                        ->icon(Heroicon::OutlinedTrash)
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn (CuotaReserva $record): bool => $this->puedeCambiar() && $record->facturaVigente === null)
                        ->action(function (CuotaReserva $record): void {
                            if ($record->facturaVigente()->exists()) {
                                return;
                            }
                            RegistroAuditoria::anotar('cuota_reserva.quitar', $record, ['importe' => $record->importe], $this->reserva()->propiedad_id);
                            $record->delete();
                            $this->filas = null;
                        }),
                ]),
            ]);
    }

    /**
     * @return list<\Filament\Forms\Components\Field>
     */
    private function campos(bool $conceptoObligatorio): array
    {
        return [
            TextInput::make('concepto')->label(__('estancias.cuotas.concepto'))->helperText(__('estancias.cuotas.concepto_ayuda'))->required($conceptoObligatorio)->maxLength(150),
            TextInput::make('importe')->label(__('ui.pagos.importe'))->required()->numeric()->minValue(0.01)->step('any')->prefix($this->reserva()->moneda),
            DatePicker::make('vence_en')->label(__('estancias.cuotas.vence'))->required(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function datos(array $data): array
    {
        return ['concepto' => blank($data['concepto'] ?? null) ? null : (string) $data['concepto'], 'importe' => Dinero::aUnidadMenor((string) $data['importe'], $this->reserva()->moneda), 'vence_en' => (string) $data['vence_en']];
    }

    private function avisoDeSuma(): ?string
    {
        $calendario = Mensualidades::de($this->reserva());
        if ($calendario['cuadra']) {
            return null;
        }

        return __('estancias.cuotas.no_cuadra', ['suma' => Formato::dinero($calendario['suma'], $this->reserva()->moneda), 'total' => Formato::dinero($calendario['total'], $this->reserva()->moneda)]);
    }

    /**
     * Cambiar el calendario es cambiar la reserva: lleva sus mismos permisos y límites.
     */
    private function puedeCambiar(): bool
    {
        return ReservaResource::canEdit($this->reserva());
    }

    private function cobraEnLinea(): bool
    {
        return $this->conPasarelas ??= Modulos::activo(Modulo::Pagos) && (new Pasarelas($this->reserva()->propiedad))->disponibles() !== [];
    }

    private function falta(CuotaReserva $cuota): int
    {
        return $cuota->importe - ($this->filaDe($cuota)['pagado'] ?? 0);
    }

    /**
     * @return array{cuota: CuotaReserva, pagado: int, estado: string}|null
     */
    private function filaDe(CuotaReserva $cuota): ?array
    {
        $this->filas ??= collect(Mensualidades::de($this->reserva())['cuotas'])->keyBy(fn (array $fila): int => $fila['cuota']->id)->all();

        return $this->filas[$cuota->id] ?? null;
    }

    private function reserva(): Reserva
    {
        /** @var Reserva $reserva */
        $reserva = $this->getOwnerRecord();

        return $reserva;
    }
}
