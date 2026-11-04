<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades\RelationManagers;

use App\Enums\EstadoEjecucion;
use App\Models\EjecucionSincronizacion;
use App\Models\Propiedad;
use App\Support\Formato;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Historial de sincronizaciones de la principal (solo consulta).
 */
class EjecucionesRelationManager extends RelationManager
{
    protected static string $relationship = 'ejecuciones';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Propiedad && ! $ownerRecord->esIndependiente();
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('ui.propiedades.ejecuciones');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('iniciada_en', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('iniciada_en')->label(__('ui.ejecuciones.iniciada'))
                    ->formatStateUsing(fn (EjecucionSincronizacion $record): string => Formato::momento($record->iniciada_en)),
                TextColumn::make('origen')->label(__('ui.ejecuciones.origen'))
                    ->formatStateUsing(fn (EjecucionSincronizacion $record): string => $record->origen->etiqueta()),
                TextColumn::make('estado')->label(__('ui.ejecuciones.estado'))->badge()
                    ->formatStateUsing(fn (EjecucionSincronizacion $record): string => $record->estado->etiqueta())
                    ->color(fn (EjecucionSincronizacion $record): string => match ($record->estado) {
                        EstadoEjecucion::Correcta => 'success',
                        EstadoEjecucion::Fallida => 'danger',
                        EstadoEjecucion::EnCurso => 'info',
                        EstadoEjecucion::Omitida => 'gray',
                    }),
                TextColumn::make('codigo_error')->label(__('ui.ejecuciones.codigo'))->placeholder('—'),
                TextColumn::make('detalle')->label(__('ui.ejecuciones.detalle'))->limit(80)->wrap()->placeholder('—'),
                TextColumn::make('duracion')->label(__('ui.ejecuciones.duracion'))
                    ->state(fn (EjecucionSincronizacion $record): ?string => $record->duracionSegundos() === null ? null : __('ui.ejecuciones.segundos', ['segundos' => $record->duracionSegundos()]))
                    ->placeholder('—'),
                TextColumn::make('bytes_descargados')->label(__('ui.ejecuciones.tamano'))
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : (string) Number::fileSize($state, 1))->placeholder('—'),
                TextColumn::make('eventos')->label(__('ui.ejecuciones.eventos'))->alignEnd(),
            ]);
    }
}
