<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Facturas;

use App\Enums\EstadoFactura;
use App\Enums\TipoDocumentoFiscal;
use App\Facturacion\ErrorFacturacion;
use App\Facturacion\GestorFacturas;
use App\Filament\Resources\Base\RecursoDeCualquierPropiedad;
use App\Filament\Resources\Facturas\Pages\ListFacturas;
use App\Models\Factura;
use App\Modulos\Modulo;
use App\Paises\Paises;
use App\Seguridad\Acceso;
use App\Support\Formato;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Lo emitido. Se factura desde la reserva; aquí se consulta, se timbra y se cancela.
 */
class FacturaResource extends RecursoDeCualquierPropiedad
{
    protected static ?string $model = Factura::class;

    protected static ?string $slug = 'facturas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCurrencyDollar;

    protected static ?int $navigationSort = 3;

    protected static function modulo(): ?Modulo
    {
        return Modulo::Facturacion;
    }

    protected static function permiso(): string
    {
        return Acceso::FACTURACION;
    }

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.dinero');
    }

    public static function getModelLabel(): string
    {
        return __('dinero.facturas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('dinero.facturas.modelos');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['propiedad', 'entidad']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('folio')->label(__('dinero.facturas.folio'))->state(fn (Factura $record): string => $record->folio())
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('numero', $search)->orWhere('serie', $search)),
                TextColumn::make('emitida_en')->label(__('dinero.facturas.fecha'))->state(fn (Factura $record): string => Formato::momento($record->emitida_en, $record->propiedad->zona_horaria))->sortable(),
                TextColumn::make('receptor.nombre')->label(__('dinero.facturas.receptor'))->limit(40),
                self::columnaPropiedad(),
                TextColumn::make('total')->label(__('dinero.facturas.total'))->alignEnd()->formatStateUsing(fn (int $state, Factura $record): string => Formato::dinero($state, $record->moneda)),
                TextColumn::make('estado')->label(__('dinero.facturas.estado'))->badge()->formatStateUsing(fn (EstadoFactura $state): string => $state->etiqueta())
                    ->color(fn (EstadoFactura $state): string => match ($state) {
                        EstadoFactura::Emitida => 'warning',
                        EstadoFactura::Registrada => 'success',
                        EstadoFactura::Cancelada => 'gray',
                    }),
            ])
            ->filters([SelectFilter::make('estado')->label(__('dinero.facturas.estado'))->options(EstadoFactura::opciones())])
            ->emptyStateHeading(__('dinero.facturas.vacio'))
            ->recordActions([
                Action::make('pdf')->label(__('dinero.facturas.descargar_pdf'))->icon(Heroicon::OutlinedDocumentArrowDown)->color('gray')
                    ->visible(fn (Factura $record): bool => $record->pdf_archivo_id !== null)
                    ->url(fn (Factura $record): string => route('archivos.ver', $record->pdf_archivo_id), shouldOpenInNewTab: true),
                Action::make('xml')->label(__('dinero.facturas.descargar_xml'))->icon(Heroicon::OutlinedCodeBracket)->color('gray')
                    ->visible(fn (Factura $record): bool => $record->xml_archivo_id !== null)
                    ->url(fn (Factura $record): string => route('archivos.ver', $record->xml_archivo_id), shouldOpenInNewTab: true),
                Action::make('timbrar')->label(__('dinero.facturas.timbrar'))->icon(Heroicon::OutlinedCheckBadge)->color('gray')
                    ->visible(fn (Factura $record): bool => $record->estado === EstadoFactura::Emitida && $record->tipo === TipoDocumentoFiscal::Factura && Paises::de($record->propiedad)->generadorFiscal() !== null)
                    ->requiresConfirmation()
                    ->action(function (Factura $record, GestorFacturas $facturas): void {
                        try {
                            $facturas->timbrar($record);
                            Notification::make()->success()->title(__('dinero.facturas.timbrada_ok'))->send();
                        } catch (ErrorFacturacion $e) {
                            Notification::make()->warning()->persistent()->title($e->getMessage())->send();
                        }
                    }),
                Action::make('cancelar')->label(__('dinero.facturas.cancelar'))->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (Factura $record): bool => $record->estado !== EstadoFactura::Cancelada)
                    ->schema([TextInput::make('motivo')->label(__('dinero.facturas.motivo'))->required()->maxLength(255)])
                    ->action(function (Factura $record, array $data, GestorFacturas $facturas): void {
                        try {
                            $facturas->cancelar($record, (string) $data['motivo']);
                            Notification::make()->success()->title(__('dinero.facturas.cancelada_ok'))->send();
                        } catch (ErrorFacturacion $e) {
                            Notification::make()->danger()->persistent()->title($e->getMessage())->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFacturas::route('/')];
    }
}
