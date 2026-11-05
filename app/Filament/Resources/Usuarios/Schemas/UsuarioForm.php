<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Usuarios\Schemas;

use App\Models\Propiedad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UsuarioForm
{
    public static function configure(Schema $schema): Schema
    {
        $esUnoMismo = fn (?User $record): bool => $record !== null && $record->getKey() === auth()->id();
        $esSuper = fn (): bool => Acceso::esSuper(Acceso::usuarioActual());

        return $schema->components([
            Section::make()->columnSpanFull()
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')->label(__('ui.usuarios.nombre'))->required()->maxLength(255),
                    TextInput::make('email')->label(__('ui.usuarios.correo'))->email()->required()->maxLength(255)->unique(ignoreRecord: true),
                    TextInput::make('password')->label(__('ui.usuarios.contrasena'))->helperText(__('ui.usuarios.contrasena_ayuda'))
                        ->password()->revealable()->minLength(12)->maxLength(200)->autocomplete('new-password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state)),
                    Select::make('idioma')->label(__('ui.usuarios.idioma'))->options(Formato::idiomas())->default('es')->required()->native(false),
                    Toggle::make('activo')->label(__('ui.usuarios.activo'))->default(true)->disabled($esUnoMismo),
                    // Solo el super administrador nombra administradores: si no, cualquiera de ellos podría repartir el acceso total.
                    Toggle::make('es_administrador')->label(__('ui.usuarios.administrador'))->helperText(__('ui.usuarios.administrador_ayuda'))
                        ->disabled($esUnoMismo)->visible($esSuper),
                    Toggle::make('es_superadministrador')->label(__('ui.usuarios.super'))->helperText(__('ui.usuarios.super_ayuda'))
                        ->disabled($esUnoMismo)->visible($esSuper),
                ]),
            Section::make(__('ui.usuarios.acceso'))->columnSpanFull()
                ->description(__('ui.usuarios.acceso_ayuda'))
                ->schema([
                    // No es una relación: lo guardan las páginas con Acceso::asignarRoles().
                    Repeater::make('accesos')->label(__('ui.usuarios.acceso'))->hiddenLabel()
                        ->schema([
                            Select::make('propiedad_id')->label(__('ui.comun.propiedad'))->required()->native(false)
                                ->options(fn (): array => Propiedad::query()->orderBy('nombre')->pluck('nombre', 'id')->all())
                                ->distinct()->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                            Select::make('rol')->label(__('ui.usuarios.rol'))->options(Acceso::opcionesRol())->required()->native(false),
                            Section::make(__('permisos.usuario.ajustes'))->description(__('permisos.usuario.ajustes_ayuda'))->columnSpanFull()->collapsed()->columns(['default' => 1, 'md' => 2])->schema([
                                CheckboxList::make('agregados')->label(__('permisos.usuario.agregados'))->options(Acceso::opcionesPermiso()),
                                CheckboxList::make('quitados')->label(__('permisos.usuario.quitados'))->options(Acceso::opcionesPermiso()),
                                Toggle::make('solo_propias')->label(__('permisos.usuario.solo_propias'))->helperText(__('permisos.usuario.solo_propias_ayuda'))->columnSpanFull(),
                                Toggle::make('sin_iniciadas')->label(__('permisos.usuario.sin_iniciadas'))->helperText(__('permisos.usuario.sin_iniciadas_ayuda'))->columnSpanFull(),
                                TextInput::make('horas_modificar')->label(__('permisos.usuario.horas_modificar'))->helperText(__('permisos.usuario.horas_ayuda'))->numeric()->minValue(1)->maxValue(65535),
                                TextInput::make('horas_cancelar')->label(__('permisos.usuario.horas_cancelar'))->helperText(__('permisos.usuario.horas_ayuda'))->numeric()->minValue(1)->maxValue(65535),
                            ]),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel(__('ui.usuarios.anadir_acceso')),
                ]),
        ]);
    }
}
