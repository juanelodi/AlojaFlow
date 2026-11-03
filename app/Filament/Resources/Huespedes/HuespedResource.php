<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes;

use App\Filament\Resources\Huespedes\Pages\ListHuespedes;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\Huespedes\Schemas\HuespedInfolist;
use App\Filament\Resources\Huespedes\Tables\HuespedesTable;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fichas de huéspedes en modo consulta (proyección de la principal).
 */
class HuespedResource extends Resource
{
    protected static ?string $model = Huesped::class;

    protected static ?string $slug = 'huespedes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'apellido1';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.datos');
    }

    public static function getModelLabel(): string
    {
        return __('ui.huespedes.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.huespedes.modelos');
    }

    public static function getRecordTitle(?Model $record): string
    {
        return $record instanceof Huesped ? $record->nombreCompleto() : '';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('propiedad_id', ReservaResource::propiedadesPermitidas());
    }

    public static function canViewAny(): bool
    {
        return ReservaResource::propiedadesPermitidas() !== [];
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Huesped && in_array($record->propiedad_id, ReservaResource::propiedadesPermitidas(), true);
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
        return HuespedInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HuespedesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHuespedes::route('/'),
            'view' => ViewHuesped::route('/{record}'),
        ];
    }
}
