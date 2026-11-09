<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RegistroAuditoria;
use App\Seguridad\Acceso;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\Rules\Password;

/**
 * La primera vez que entra quien se instaló sin navegador (D-164): su contraseña la eligió la máquina y nadie la
 * conoce, así que crea la suya sin escribir la actual. Hasta entonces no ve otra pantalla (ContrasenaPorCrear).
 *
 * @property-read Schema $form
 */
class CrearContrasena extends Page
{
    protected string $view = 'filament.pages.crear-contrasena';

    protected static ?string $slug = 'crear-contrasena';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $datos = [];

    public static function canAccess(): bool
    {
        return (bool) Acceso::usuarioActual()?->debe_crear_contrasena;
    }

    public function getTitle(): string|Htmlable
    {
        return __('permisos.crear_contrasena.titulo');
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('datos')->components([
            Section::make()->description(__('permisos.crear_contrasena.ayuda', ['correo' => (string) Acceso::usuarioActual()?->email]))->schema([
                TextInput::make('contrasena')->label(__('permisos.crear_contrasena.nueva'))->password()->revealable()
                    ->autocomplete('new-password')->required()->maxLength(200)->rule(Password::default())->same('confirmacion'),
                TextInput::make('confirmacion')->label(__('permisos.crear_contrasena.confirmar'))->password()->revealable()
                    ->autocomplete('new-password')->required()->dehydrated(false),
            ]),
        ]);
    }

    public function guardar(): void
    {
        $usuario = Acceso::usuarioActual();
        abort_unless($usuario !== null && $usuario->debe_crear_contrasena, 403);
        $contrasena = (string) $this->form->getState()['contrasena'];
        $usuario->forceFill(['password' => $contrasena, 'debe_crear_contrasena' => false])->save();
        // La sesión guarda la huella de la contraseña: sin esto, AuthenticateSession la cerraría en la siguiente página.
        session()->put('password_hash_'.Filament::getAuthGuard(), $usuario->getAuthPassword());
        RegistroAuditoria::anotar('usuario.crear_contrasena', $usuario);
        $this->datos = [];
        Notification::make()->success()->title(__('permisos.crear_contrasena.lista'))->send();
        $this->redirect(Resumen::getUrl());
    }

}
