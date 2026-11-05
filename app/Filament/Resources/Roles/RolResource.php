<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Demo\ModoDemo;
use App\Filament\Resources\Roles\Pages\CreateRol;
use App\Filament\Resources\Roles\Pages\EditRol;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Seguridad\Acceso;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Los roles y sus permisos (D-075): se pueden ajustar los de fábrica y crear otros.
 */
class RolResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $slug = 'roles';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return __('ui.nav.configuracion');
    }

    public static function getModelLabel(): string
    {
        return __('permisos.roles.modelo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('permisos.roles.modelos');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('propiedad_id');
    }

    private static function esAdministrador(): bool
    {
        return (bool) Acceso::usuarioActual()?->es_administrador && ! ModoDemo::activo();
    }

    public static function canViewAny(): bool
    {
        return self::esAdministrador();
    }

    public static function canCreate(): bool
    {
        return self::esAdministrador();
    }

    public static function canEdit(Model $record): bool
    {
        return self::esAdministrador();
    }

    /**
     * Los de fábrica se pueden ajustar, pero no borrar; uno en uso tampoco, para no dejar a nadie sin acceso.
     */
    public static function canDelete(Model $record): bool
    {
        return self::esAdministrador() && ! array_key_exists((string) $record->getAttribute('name'), Acceso::ROLES) && self::enUso($record) === 0;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function enUso(Model $record): int
    {
        return DB::table('model_has_roles')->where('role_id', $record->getKey())->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->description(__('permisos.roles.ayuda'))->schema([
                TextInput::make('name')->label(__('permisos.roles.nombre'))->required()->maxLength(60)
                    ->disabled(fn (?Role $record): bool => $record !== null && array_key_exists($record->name, Acceso::ROLES))
                    ->unique(ignoreRecord: true),
                CheckboxList::make('permisos')->label(__('permisos.roles.permisos'))->options(Acceso::opcionesPermiso())->columns(['default' => 1, 'md' => 2]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('name')->label(__('permisos.roles.nombre'))->formatStateUsing(fn (string $state): string => Acceso::etiquetaRol($state))->searchable(),
                TextColumn::make('cuantos')->label(__('permisos.roles.permisos'))->state(fn (Role $record): int => $record->permissions()->count()),
                TextColumn::make('uso')->label(__('permisos.roles.en_uso'))->state(fn (Role $record): int => self::enUso($record)),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRol::route('/create'),
            'edit' => EditRol::route('/{record}/edit'),
        ];
    }
}
