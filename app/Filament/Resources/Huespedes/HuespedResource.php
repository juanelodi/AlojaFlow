<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Huespedes;

use App\Filament\Resources\Huespedes\Pages\CreateHuesped;
use App\Filament\Resources\Huespedes\Pages\EditHuesped;
use App\Filament\Resources\Huespedes\Pages\ListHuespedes;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\Huespedes\Schemas\HuespedForm;
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
use Illuminate\Support\Facades\DB;

/**
 * Fichas de huéspedes: se editan en las propiedades independientes y, en las subordinadas, son
 * de solo consulta porque vienen de la principal.
 */
class HuespedResource extends Resource
{
    protected static ?string $model = Huesped::class;

    protected static bool $hasTitleCaseModelLabel = false;

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
        return ReservaResource::propiedadesGestionables() !== [];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Huesped && ! $record->sincronizada
            && in_array($record->propiedad_id, ReservaResource::propiedadesGestionables(), true);
    }

    /**
     * Solo una ficha sin reservas: las reservas conservan a sus huéspedes.
     */
    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record) && $record instanceof Huesped
            && $record->reservasComoTitular()->doesntExist()
            && DB::table('reservas_huespedes')->where('huesped_id', $record->id)->doesntExist();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return HuespedForm::configure($schema);
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
            'create' => CreateHuesped::route('/create'),
            'view' => ViewHuesped::route('/{record}'),
            'edit' => EditHuesped::route('/{record}/edit'),
        ];
    }
}
