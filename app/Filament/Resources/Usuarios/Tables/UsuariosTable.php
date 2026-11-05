<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Resources\Usuarios\Tables;

use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
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
                IconColumn::make('es_superadministrador')->label(__('ui.usuarios.super'))->boolean(),
                IconColumn::make('es_administrador')->label(__('ui.usuarios.administrador'))->boolean(),
                IconColumn::make('activo')->label(__('ui.usuarios.activo'))->boolean(),
                IconColumn::make('dos_pasos')->label(__('ui.usuarios.dos_pasos'))->boolean()
                    ->state(fn (User $record): bool => filled($record->app_authentication_secret)),
                TextColumn::make('ultimo_acceso_en')->label(__('ui.usuarios.ultimo_acceso'))
                    ->formatStateUsing(fn (User $record): string => Formato::momento($record->ultimo_acceso_en))->placeholder('—'),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                // Para quien perdió el teléfono. A uno mismo no: eso se hace en el perfil, y con el código.
                Action::make('quitarDosPasos')
                    ->label(__('permisos.dos_pasos.quitar'))
                    ->icon(Heroicon::OutlinedShieldExclamation)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('permisos.dos_pasos.quitar_texto'))
                    ->schema([TextInput::make('contrasena')->label(__('actualizaciones.contrasena'))->password()->required()->currentPassword()])
                    ->visible(fn (User $record): bool => Acceso::esSuper(Acceso::usuarioActual()) && filled($record->app_authentication_secret) && $record->getKey() !== auth()->id())
                    ->action(function (User $record): void {
                        $record->saveAppAuthenticationSecret(null);
                        $record->saveAppAuthenticationRecoveryCodes(null);
                        RegistroAuditoria::anotar('usuario.quitar_dos_pasos', $record);
                        Notification::make()->success()->title(__('permisos.dos_pasos.quitada', ['usuario' => $record->name]))->send();
                    }),
            ]);
    }
}
