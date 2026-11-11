<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Unidades;

use App\Filament\Resources\Unidades\Pages\CreateUnidad;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Pages\ListUnidades;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Filament\Resources\Unidades\Tables\UnidadesTable;
use App\Models\Unidad;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Habitaciones, cabañas o departamentos. Se crean y cambian en las propiedades independientes; las
 * de una subordinada vienen de su principal y solo se consultan.
 */
class UnidadResource extends Resource
{
    protected static ?string $model = Unidad::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'unidades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('ui.unidades.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.unidades.modelos');
    }

    /**
     * @return list<int>
     */
    public static function propiedadesVisibles(): array
    {
        return Acceso::propiedades(Acceso::usuarioActual(), Acceso::CATALOGO);
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
        return parent::getEloquentQuery()->whereIn('propiedad_id', self::propiedadesVisibles());
    }

    public static function canViewAny(): bool
    {
        return self::propiedadesVisibles() !== [];
    }

    public static function canCreate(): bool
    {
        return self::propiedadesGestionables() !== [];
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof Unidad && ! $record->sincronizada
            && in_array($record->propiedad_id, self::propiedadesGestionables(), true);
    }

    /**
     * Solo una unidad sin reservas: con historial se desactiva, para no perderlo.
     */
    public static function canDelete(Model $record): bool
    {
        return self::canEdit($record) && $record instanceof Unidad && $record->reservas()->doesntExist();
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return UnidadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UnidadesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnidades::route('/'),
            'create' => CreateUnidad::route('/create'),
            'edit' => EditUnidad::route('/{record}/edit'),
        ];
    }
}
