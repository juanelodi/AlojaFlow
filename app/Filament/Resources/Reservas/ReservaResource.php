<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas;

use App\Filament\Resources\Reservas\Pages\ListReservas;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\Schemas\ReservaInfolist;
use App\Filament\Resources\Reservas\Tables\ReservasTable;
use App\Models\Reserva;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Reservas en modo consulta: en la fase 1 se cambian en la principal.
 */
class ReservaResource extends Resource
{
    protected static ?string $model = Reserva::class;

    protected static ?string $slug = 'reservas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'numero';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.datos');
    }

    public static function getModelLabel(): string
    {
        return __('ui.reservas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.reservas.modelos');
    }

    /**
     * Quien ve la operación o los datos de una propiedad ve sus reservas.
     *
     * @return list<int>
     */
    public static function propiedadesPermitidas(): array
    {
        $usuario = Acceso::usuarioActual();

        return array_values(array_unique(array_merge(
            Acceso::propiedades($usuario, Acceso::DATOS),
            Acceso::propiedades($usuario, Acceso::OPERACION),
        )));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('propiedad_id', self::propiedadesPermitidas());
    }

    public static function canViewAny(): bool
    {
        return self::propiedadesPermitidas() !== [];
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Reserva && in_array($record->propiedad_id, self::propiedadesPermitidas(), true);
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

    public static function infolist(Schema $schema): Schema
    {
        return ReservaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservas::route('/'),
            'view' => ViewReserva::route('/{record}'),
        ];
    }
}
