<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes\Tables;

use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Servicios\Buscador;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class HuespedesTable
{
    private const OPCIONES_MAXIMAS = 300;

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['contactos', 'propiedad'])->withCount('reservasComoTitular'))
            ->searchable()
            ->searchUsing(fn (Builder $query, string $search): Builder => Buscador::huespedes($query, $search))
            ->searchPlaceholder(__('ui.huespedes.buscar'))
            ->columns([
                TextColumn::make('id')->label(__('ui.huespedes.codigo'))->state(fn (Huesped $record): string => $record->codigo())->toggleable()
                    // Por lo que se ve: en una ficha sincronizada, el número de la principal.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(DB::raw('CAST(COALESCE(clave_origen, huespedes.id) AS UNSIGNED)'), $direction)),
                TextColumn::make('apellido1')->label(__('ui.huespedes.nombre'))->sortable()->wrap()
                    ->formatStateUsing(fn (Huesped $record): string => $record->nombreCompleto()),
                TextColumn::make('contactos.valor')->label(__('ui.huespedes.contactos'))
                    ->state(fn (Huesped $record): array => $record->contactos->pluck('valor')->take(2)->all())
                    ->listWithLineBreaks()->placeholder('—'),
                TextColumn::make('nacionalidad_texto')->label(__('ui.huespedes.nacionalidad'))->placeholder('—')->toggleable(),
                TextColumn::make('domicilio_ciudad')->label(__('ui.huespedes.ciudad'))->placeholder('—')->toggleable(),
                TextColumn::make('domicilio_region')->label(__('ui.huespedes.region'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('domicilio_pais_texto')->label(__('ui.huespedes.pais'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('propiedad.nombre')->label(__('ui.comun.propiedad'))
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1),
                TextColumn::make('reservas_como_titular_count')->label(__('ui.huespedes.reservas'))->alignEnd()->sortable(),
            ])
            ->defaultSort('apellido1')
            ->filtersFormWidth(Width::FourExtraLarge)
            ->filtersFormColumns(['default' => 1, 'sm' => 2, 'lg' => 3])
            ->filters([
                // Se mira si existen y no el modo: una propiedad que pasó a independiente los conserva. Por qué no es una casilla, en ReservasTable.
                TernaryFilter::make('vigentes')->label(__('ui.reservas.eliminada'))
                    ->visible(fn (): bool => once(fn (): bool => Huesped::query()->whereIn('propiedad_id', ReservaResource::propiedadesPermitidas())->whereNotNull('eliminado_en_principal_en')->exists()))
                    ->placeholder(__('ui.reservas.solo_vigentes'))->trueLabel(__('ui.reservas.con_eliminadas'))->falseLabel(__('ui.reservas.solo_eliminadas'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query,
                        false: fn (Builder $query): Builder => $query->whereNotNull('eliminado_en_principal_en'),
                        blank: fn (Builder $query): Builder => $query->whereNull('eliminado_en_principal_en'),
                    ),
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))->multiple()
                    ->visible(fn (): bool => count(ReservaResource::propiedadesPermitidas()) > 1)
                    ->options(fn (): array => Propiedad::query()->whereIn('id', ReservaResource::propiedadesPermitidas())->orderBy('nombre')->pluck('nombre', 'id')->all()),
                self::filtroDeTexto('nacionalidad_texto', __('ui.huespedes.nacionalidad')),
                self::filtroDeTexto('domicilio_pais_texto', __('ui.huespedes.pais')),
                self::filtroDeTexto('domicilio_region', __('ui.huespedes.region')),
                self::filtroDeTexto('domicilio_ciudad', __('ui.huespedes.ciudad')),
            ])
            ->recordActions([ViewAction::make(), EditAction::make()->visible(fn (Huesped $record): bool => HuespedResource::canEdit($record))])
            ->recordUrl(fn (Huesped $record): string => HuespedResource::getUrl('view', ['record' => $record]));
    }

    /**
     * Las opciones salen de los huéspedes que quien mira puede ver.
     */
    private static function filtroDeTexto(string $campo, string $etiqueta): SelectFilter
    {
        return SelectFilter::make($campo)->label($etiqueta)->multiple()->searchable()
            ->options(function () use ($campo): array {
                $valores = HuespedResource::getEloquentQuery()->whereNotNull($campo)->where($campo, '!=', '')->distinct()->orderBy($campo)->limit(self::OPCIONES_MAXIMAS)->pluck($campo)->map(fn (mixed $v): string => (string) $v)->all();

                return $valores === [] ? [] : array_combine($valores, $valores);
            });
    }
}
