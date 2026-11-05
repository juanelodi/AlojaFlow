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
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\EnlacePago;
use App\Models\Reserva;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Pagos\GestorEnlacesPago;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Los enlaces de pago de una reserva, para copiarlos o anularlos. Se crean con la acción de la reserva.
 */
class EnlacesPagoRelationManager extends RelationManager
{
    protected static string $relationship = 'enlacesPago';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('operacion.enlaces.titulo');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Modulos::activo(Modulo::Pagos) && $ownerRecord instanceof Reserva && in_array($ownerRecord->propiedad_id, ReservaResource::propiedadesGestionables(), true) && $ownerRecord->enlacesPago()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('creado_en', 'desc')
            ->paginated(false)
            ->columns([
                TextColumn::make('concepto')->label(__('operacion.enlaces.concepto_campo'))->wrap(),
                TextColumn::make('importe')->label(__('ui.pagos.importe'))->alignEnd()->formatStateUsing(fn (EnlacePago $record): string => Formato::dinero($record->importe, $record->moneda)),
                TextColumn::make('vence_en')->label(__('operacion.enlaces.vence_columna'))->formatStateUsing(fn (EnlacePago $record): string => Formato::momento($record->vence_en, $this->reserva()->propiedad->zona_horaria)),
                TextColumn::make('estado')->label(__('ui.comun.estado'))->badge()
                    ->state(fn (EnlacePago $record): string => __('operacion.enlaces.estados.'.$record->estado()))
                    ->color(fn (EnlacePago $record): string => match ($record->estado()) {
                        'pagado' => 'success',
                        'vigente' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('url')->label(__('operacion.enlaces.direccion'))->limit(32)->copyable()->placeholder('—')
                    ->state(fn (EnlacePago $record): ?string => $record->estado() === 'vigente' ? app(GestorEnlacesPago::class)->url($record, Mensajero::idiomaDe($this->reserva())) : null),
            ])
            ->recordActions([
                Action::make('whatsapp')->label(__('operacion.mensajes.whatsapp'))->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('gray')
                    ->visible(fn (EnlacePago $record): bool => $record->estado() === 'vigente')
                    ->url(function (EnlacePago $record): string {
                        $reserva = $this->reserva();
                        $idioma = Mensajero::idiomaDe($reserva);
                        $texto = __('operacion.enlaces.mensaje', ['concepto' => $record->concepto, 'importe' => Formato::dinero($record->importe, $record->moneda), 'enlace' => (string) app(GestorEnlacesPago::class)->url($record, $idioma)], $idioma);

                        return VariablesMensaje::enlaceWhatsapp(Mensajero::telefonoDe($reserva), $texto, $reserva->propiedad->pais);
                    }, shouldOpenInNewTab: true),
                Action::make('anular')->label(__('operacion.enlaces.anular'))->icon(Heroicon::OutlinedXMark)->color('danger')->requiresConfirmation()
                    ->visible(fn (EnlacePago $record): bool => $record->estado() === 'vigente')
                    ->action(fn (EnlacePago $record) => app(GestorEnlacesPago::class)->anular($record)),
            ]);
    }

    private function reserva(): Reserva
    {
        /** @var Reserva $reserva */
        $reserva = $this->getOwnerRecord();

        return $reserva;
    }
}
