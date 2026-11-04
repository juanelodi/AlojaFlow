<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas\Schemas;

use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\Reserva;
use App\Support\Formato;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $dinero = fn (string $campo): \Closure => fn (Reserva $record): string => Formato::dinero((int) $record->{$campo}, $record->moneda);

        return $schema->components([
            Section::make(__('ui.reservas.estancia'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema([
                    TextEntry::make('propiedad.nombre')->label(__('ui.comun.propiedad')),
                    TextEntry::make('unidad.nombre')->label(__('ui.comun.unidad')),
                    TextEntry::make('llegada')->label(__('ui.comun.llegada'))->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->llegada, 'dddd ll')),
                    TextEntry::make('salida')->label(__('ui.comun.salida'))->formatStateUsing(fn (Reserva $record): string => Formato::fecha($record->salida, 'dddd ll')),
                    TextEntry::make('noches')->label(__('ui.comun.noches')),
                    TextEntry::make('personas')->label(__('ui.comun.personas')),
                    TextEntry::make('estado')->label(__('ui.comun.estado'))->badge()->formatStateUsing(fn (Reserva $record): string => $record->estado->etiqueta()),
                    TextEntry::make('tipo')->label(__('ui.reservas.tipo'))->formatStateUsing(fn (Reserva $record): string => $record->tipo->etiqueta()),
                    TextEntry::make('entrada_registrada_en')->label(__('ui.reservas.entrada'))->formatStateUsing(fn (Reserva $record): string => Formato::momento($record->entrada_registrada_en, self::zona($record)))->placeholder('—'),
                    TextEntry::make('salida_registrada_en')->label(__('ui.reservas.salida_registrada'))->formatStateUsing(fn (Reserva $record): string => Formato::momento($record->salida_registrada_en, self::zona($record)))->placeholder('—'),
                    TextEntry::make('eliminada_en_principal_en')->label(__('ui.reservas.eliminada'))->color('danger')
                        ->formatStateUsing(fn (Reserva $record): string => Formato::momento($record->eliminada_en_principal_en, self::zona($record)))
                        ->visible(fn (Reserva $record): bool => $record->eliminada_en_principal_en !== null),
                ]),
            Section::make(__('ui.comun.huesped'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextEntry::make('titular.apellido1')->label(__('ui.comun.huesped'))
                        ->formatStateUsing(fn (Reserva $record): string => $record->titular?->nombreCompleto() ?? '—')
                        ->url(fn (Reserva $record): ?string => $record->titular ? \App\Filament\Resources\Huespedes\HuespedResource::getUrl('view', ['record' => $record->titular]) : null),
                    TextEntry::make('contacto')->label(__('ui.comun.contacto'))
                        ->state(fn (Reserva $record): array => $record->titular?->contactos->pluck('valor')->all() ?? [])
                        ->listWithLineBreaks()->placeholder('—'),
                    TextEntry::make('acompanantes')->label(__('ui.reservas.acompanantes'))
                        // HotelDruid guarda también al titular entre los huéspedes de la reserva.
                        ->state(fn (Reserva $record): array => $record->huespedes->reject(fn (Huesped $h): bool => $h->id === $record->titular_id)
                            ->map(function (Huesped $h): string {
                                $parentesco = data_get($h->getRelationValue('pivot'), 'parentesco_texto');

                                return $h->nombreCompleto().($parentesco ? ' ('.$parentesco.')' : '');
                            })->values()->all())
                        ->listWithLineBreaks()->placeholder('—'),
                ]),
            Section::make(__('ui.reservas.dinero'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->schema([
                    TextEntry::make('total')->label(__('ui.comun.total'))->formatStateUsing($dinero('total')),
                    TextEntry::make('pagado')->label(__('ui.comun.pagado'))->formatStateUsing($dinero('pagado')),
                    TextEntry::make('pendiente')->label(__('ui.comun.pendiente'))->formatStateUsing($dinero('pendiente'))
                        ->color(fn (Reserva $record): ?string => $record->pendiente > 0 ? 'warning' : null)->weight('bold'),
                    TextEntry::make('deposito')->label(__('ui.reservas.deposito'))->formatStateUsing($dinero('deposito')),
                    TextEntry::make('descuento')->label(__('ui.reservas.descuento'))->formatStateUsing($dinero('descuento')),
                    TextEntry::make('comision')->label(__('ui.reservas.comision'))->formatStateUsing($dinero('comision')),
                    TextEntry::make('tarifa_nombre')->label(__('ui.reservas.tarifa'))->placeholder('—'),
                ]),
            Section::make(__('ui.comun.origen'))->columnSpanFull()
                ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                ->collapsible()
                ->schema([
                    TextEntry::make('numero')->label(__('ui.reservas.numero')),
                    TextEntry::make('origen_texto')->label(__('ui.comun.origen'))->placeholder('—'),
                    TextEntry::make('grupo_codigo')->label(__('ui.reservas.grupo'))->placeholder('—'),
                    TextEntry::make('codigo_publico')->label(__('ui.reservas.codigo_publico'))->copyable(),
                    TextEntry::make('creada_en_origen')->label(__('ui.reservas.creada_origen'))->formatStateUsing(fn (Reserva $record): string => Formato::momento($record->creada_en_origen, self::zona($record)))->placeholder('—')
                        ->visible(fn (Reserva $record): bool => $record->sincronizada),
                    TextEntry::make('creado_en')->label(__('ui.reservas.creada'))->formatStateUsing(fn (Reserva $record): string => Formato::momento($record->getAttribute('creado_en'), self::zona($record)))
                        ->visible(fn (Reserva $record): bool => ! $record->sincronizada),
                    TextEntry::make('comentario')->label(__('ui.reservas.comentario'))->columnSpanFull()->placeholder('—'),
                ]),
            Section::make(__('ui.reservas.eventos'))->columnSpanFull()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextEntry::make('lista_eventos')->label(__('ui.reservas.eventos'))->hiddenLabel()
                        ->state(fn (Reserva $record): array => $record->eventos()->latest('ocurrido_en')->limit(20)->get()
                            ->map(fn (EventoReserva $e): string => Formato::momento($e->ocurrido_en).' · '.$e->tipo->etiqueta())->all())
                        ->listWithLineBreaks()->bulleted()->placeholder('—'),
                ]),
        ]);
    }

    /**
     * La zona que eligió el usuario o, si no eligió ninguna, la de la propiedad.
     */
    private static function zona(Reserva $reserva): string
    {
        return (string) (auth()->user()->zona_horaria ?? $reserva->propiedad->zona_horaria);
    }
}
