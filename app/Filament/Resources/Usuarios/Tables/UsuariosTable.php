<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Usuarios\Tables;

use App\Models\User;
use App\Support\Formato;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsuariosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label(__('ui.usuarios.nombre'))->searchable()->sortable(),
                TextColumn::make('email')->label(__('ui.usuarios.correo'))->searchable(),
                IconColumn::make('es_administrador')->label(__('ui.usuarios.administrador'))->boolean(),
                IconColumn::make('activo')->label(__('ui.usuarios.activo'))->boolean(),
                IconColumn::make('dos_pasos')->label(__('ui.usuarios.dos_pasos'))->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('ultimo_acceso_en')->label(__('ui.usuarios.ultimo_acceso'))
                    ->formatStateUsing(fn (User $record): string => Formato::momento($record->ultimo_acceso_en))->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make()]);
    }
}
