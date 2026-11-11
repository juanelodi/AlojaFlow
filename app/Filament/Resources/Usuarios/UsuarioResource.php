<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Resources\Usuarios;

use App\Demo\ModoDemo;
use App\Filament\Resources\Usuarios\Pages\CreateUsuario;
use App\Filament\Resources\Usuarios\Pages\EditUsuario;
use App\Filament\Resources\Usuarios\Pages\ListUsuarios;
use App\Filament\Resources\Usuarios\Schemas\UsuarioForm;
use App\Filament\Resources\Usuarios\Tables\UsuariosTable;
use App\Models\User;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Usuarios del personal y su rol en cada propiedad. Solo el administrador.
 */
class UsuarioResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 7;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('ui.usuarios.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('ui.usuarios.modelos');
    }

    private static function esAdministrador(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function canViewAny(): bool
    {
        return self::esAdministrador();
    }

    /**
     * El soporte de AlojaFlow ve al equipo pero no crea ni cambia a nadie (D-166): no debe quedarle un acceso propio
     * cuando vence el suyo.
     */
    private static function puedeAdministrar(): bool
    {
        return self::esAdministrador() && ! Acceso::usuarioActual()?->es_soporte;
    }

    public static function canCreate(): bool
    {
        return self::puedeAdministrar();
    }

    /**
     * A un administrador solo lo toca un super administrador: cambiarle la contraseña sería entrar como él (D-070).
     */
    private static function puedeTocar(Model $record): bool
    {
        return ! $record->getAttribute('es_administrador') || Acceso::esSuper(Acceso::usuarioActual()) || $record->getKey() === auth()->id();
    }

    public static function canEdit(Model $record): bool
    {
        return self::puedeAdministrar() && self::puedeTocar($record);
    }

    public static function canDelete(Model $record): bool
    {
        // Nadie se borra a sí mismo; mejor desactivar que borrar.
        return self::puedeAdministrar() && self::puedeTocar($record) && $record->getKey() !== auth()->id();
    }

    public static function form(Schema $schema): Schema
    {
        return UsuarioForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsuariosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsuarios::route('/'),
            'create' => CreateUsuario::route('/create'),
            'edit' => EditUsuario::route('/{record}/edit'),
        ];
    }
}
