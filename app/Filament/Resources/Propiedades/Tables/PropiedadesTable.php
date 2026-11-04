<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\Tables;

use App\Enums\EstadoPrincipal;
use App\Filament\Resources\Propiedades\AccionesSincronizacion;
use App\Models\Propiedad;
use App\Support\Formato;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PropiedadesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('principal'))
            ->columns([
                TextColumn::make('nombre')->label(__('ui.propiedades.nombre'))->searchable()->sortable(),
                TextColumn::make('codigo')->label(__('ui.propiedades.codigo'))->toggleable(),
                TextColumn::make('modo')->label(__('ui.propiedades.modo_corto'))->badge()
                    ->formatStateUsing(fn (Propiedad $record): string => $record->modo->etiqueta().($record->es_demo ? ' · '.__('ui.demo.etiqueta') : ''))
                    ->color(fn (Propiedad $record): string => $record->esIndependiente() ? 'primary' : 'gray'),
                TextColumn::make('principal.estado')->label(__('ui.propiedades.estado'))->badge()
                    ->formatStateUsing(fn (Propiedad $record): string => $record->principal?->estado === EstadoPrincipal::Pausada
                        ? __('ui.propiedades.pausada', ['codigo' => (string) $record->principal->codigo_pausa])
                        : ($record->principal?->estado->etiqueta() ?? '—'))
                    ->color(fn (Propiedad $record): string => match (true) {
                        $record->principal?->estado === EstadoPrincipal::Pausada => 'danger',
                        ($record->principal->fallos_consecutivos ?? 0) > 0 => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('principal.fecha_copia')->label(__('ui.propiedades.datos_al'))
                    ->formatStateUsing(fn (Propiedad $record): string => Formato::momento($record->principal?->fecha_copia, $record->zona_horaria))
                    ->placeholder('—'),
                TextColumn::make('principal.ultima_correcta_en')->label(__('ui.propiedades.ultima_correcta'))
                    ->formatStateUsing(fn (Propiedad $record): string => Formato::momento($record->principal?->ultima_correcta_en, $record->zona_horaria))
                    ->placeholder('—')->toggleable(),
                TextColumn::make('principal.version_esquema')->label(__('ui.propiedades.version'))->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('activa')->label(__('ui.propiedades.activa'))->boolean(),
            ])
            ->defaultSort('nombre')
            ->recordActions([
                AccionesSincronizacion::sincronizar()->iconButton(),
                EditAction::make(),
            ]);
    }
}
