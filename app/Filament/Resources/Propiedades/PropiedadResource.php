<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Propiedades;

use App\Demo\ModoDemo;
use App\Filament\Resources\Propiedades\Pages\CreatePropiedad;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\Propiedades\Pages\ListPropiedades;
use App\Filament\Resources\Propiedades\RelationManagers\EjecucionesRelationManager;
use App\Filament\Resources\Propiedades\Schemas\PropiedadForm;
use App\Filament\Resources\Propiedades\Tables\PropiedadesTable;
use App\Models\Propiedad;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Propiedades y su instalación principal de HotelDruid. Solo el administrador.
 */
class PropiedadResource extends Resource
{
    protected static ?string $model = Propiedad::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'propiedades';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $recordTitleAttribute = 'nombre';

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('ui.propiedades.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.propiedades.modelos');
    }

    private static function esAdministrador(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador;
    }

    public static function canViewAny(): bool
    {
        return self::esAdministrador();
    }

    public static function canCreate(): bool
    {
        return self::esAdministrador() && ! ModoDemo::activo();
    }

    public static function canEdit(Model $record): bool
    {
        // En la demostración no: conectar una principal haría peticiones a cualquier dirección.
        return self::esAdministrador() && ! ModoDemo::activo();
    }

    public static function canDelete(Model $record): bool
    {
        return self::esAdministrador() && ! ModoDemo::activo();
    }

    public static function form(Schema $schema): Schema
    {
        return PropiedadForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropiedadesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [EjecucionesRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropiedades::route('/'),
            'create' => CreatePropiedad::route('/create'),
            'edit' => EditPropiedad::route('/{record}/edit'),
        ];
    }
}
