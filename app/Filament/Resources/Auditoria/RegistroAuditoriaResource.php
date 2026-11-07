<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Auditoria;

use App\Demo\ModoDemo;
use App\Filament\Campos\RangoFechas;
use App\Filament\Resources\Auditoria\Pages\ListRegistrosAuditoria;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Seguridad\EtiquetasAuditoria;
use App\Support\Formato;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Quién cambió qué: consulta del registro de auditoría. Solo el administrador; nadie lo edita.
 */
class RegistroAuditoriaResource extends Resource
{
    protected static ?string $model = RegistroAuditoria::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'auditoria';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 75;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.sistema');
    }

    public static function getModelLabel(): string
    {
        return __('ui.auditoria.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.auditoria.modelos');
    }

    public static function canViewAny(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('usuario'))
            ->defaultSort('creado_en', 'desc')
            ->columns([
                TextColumn::make('creado_en')->label(__('ui.auditoria.cuando'))->sortable()
                    ->formatStateUsing(fn (RegistroAuditoria $record): string => Formato::momento($record->creado_en)),
                TextColumn::make('usuario.name')->label(__('ui.auditoria.quien'))->placeholder(__('ui.auditoria.sistema'))->searchable(),
                TextColumn::make('accion')->label(__('ui.auditoria.accion'))->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => EtiquetasAuditoria::accion($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => EtiquetasAuditoria::buscarAccion($query, $search)),
                TextColumn::make('modelo')->label(__('ui.auditoria.objeto'))
                    ->state(fn (RegistroAuditoria $record): ?string => EtiquetasAuditoria::objeto($record->modelo_tipo, $record->modelo_id))
                    ->searchable(query: fn (Builder $query, string $search): Builder => EtiquetasAuditoria::buscarObjeto($query, $search))
                    ->placeholder('—'),
                TextColumn::make('cambios')->label(__('ui.auditoria.detalle'))->limit(90)->wrap()->placeholder('—')
                    ->state(fn (RegistroAuditoria $record): ?string => EtiquetasAuditoria::detalle($record->cambios)),
                TextColumn::make('ip')->label('IP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('user_id')->label(__('ui.auditoria.quien'))
                    ->options(fn (): array => User::query()->orderBy('name')->pluck('name', 'id')->all()),
                SelectFilter::make('propiedad_id')->label(__('ui.comun.propiedad'))
                    ->options(fn (): array => Propiedad::query()->orderBy('nombre')->pluck('nombre', 'id')->all()),
                Filter::make('fecha')->label(__('ui.auditoria.cuando'))
                    ->schema([
                        DatePicker::make('desde')->extraInputAttributes(RangoFechas::inicio('filtro_fecha'))->label(__('ui.indicadores.desde')),
                        DatePicker::make('hasta')->extraInputAttributes(RangoFechas::fin('filtro_fecha'))->label(__('ui.indicadores.hasta')),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['desde'] ?? null, fn (Builder $q, string $desde): Builder => $q->whereDate('creado_en', '>=', $desde))
                        ->when($data['hasta'] ?? null, fn (Builder $q, string $hasta): Builder => $q->whereDate('creado_en', '<=', $hasta))),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListRegistrosAuditoria::route('/')];
    }
}
