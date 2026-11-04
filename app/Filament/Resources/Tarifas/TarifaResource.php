<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Tarifas;

use App\Filament\Resources\Tarifas\Pages\CreateTarifa;
use App\Filament\Resources\Tarifas\Pages\EditTarifa;
use App\Filament\Resources\Tarifas\Pages\ListTarifas;
use App\Filament\Resources\Tarifas\Schemas\TarifaForm;
use App\Filament\Resources\Tarifas\Tables\TarifasTable;
use App\Models\Tarifa;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tarifas por noche con temporadas, de las propiedades independientes (D-036).
 */
class TarifaResource extends Resource
{
    protected static ?string $model = Tarifa::class;

    protected static ?string $slug = 'tarifas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('ui.tarifas.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.tarifas.modelos');
    }

    /**
     * @return list<int>
     */
    public static function propiedadesGestionables(): array
    {
        return Acceso::propiedadesIndependientes(Acceso::usuarioActual(), Acceso::CATALOGO);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('propiedad_id', self::propiedadesGestionables());
    }

    public static function canViewAny(): bool
    {
        return self::propiedadesGestionables() !== [];
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Tarifa && in_array($record->propiedad_id, self::propiedadesGestionables(), true);
    }

    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return TarifaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TarifasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTarifas::route('/'),
            'create' => CreateTarifa::route('/create'),
            'edit' => EditTarifa::route('/{record}/edit'),
        ];
    }
}
