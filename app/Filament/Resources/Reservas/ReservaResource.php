<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Reservas;

use App\Enums\EstadoReserva;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Filament\Resources\Reservas\Pages\ListReservas;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\HuespedesRelationManager;
use App\Filament\Resources\Reservas\RelationManagers\NotasRelationManager;
use App\Filament\Resources\Reservas\RelationManagers\PagosRelationManager;
use App\Filament\Resources\Reservas\Schemas\ReservaForm;
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
 * Reservas: en una propiedad independiente se crean y cambian aquí; en una subordinada, solo se
 * consultan y se cambian en la principal.
 */
class ReservaResource extends Resource
{
    protected static ?string $model = Reserva::class;

    protected static bool $hasTitleCaseModelLabel = false;

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

    /**
     * Propiedades independientes donde el usuario puede crear y cambiar reservas.
     *
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::RESERVAS);
    }

    public static function canCreate(): bool
    {
        return self::propiedadesGestionables() !== [];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Reserva && ! $record->sincronizada && $record->estado !== EstadoReserva::Cancelada
            && in_array($record->propiedad_id, self::propiedadesGestionables(), true);
    }

    /**
     * Las reservas no se borran: se cancelan y queda su historial.
     */
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ReservaForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservaInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [PagosRelationManager::class, HuespedesRelationManager::class, NotasRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservas::route('/'),
            'create' => CreateReserva::route('/create'),
            'view' => ViewReserva::route('/{record}'),
            'edit' => EditReserva::route('/{record}/edit'),
        ];
    }
}
