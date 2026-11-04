<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Demo\ModoDemo;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * El inicio de sesión de siempre; en la demostración pública ya viene con el usuario de prueba.
 */
class IniciarSesion extends Login
{
    public function mount(): void
    {
        parent::mount();

        if (ModoDemo::activo()) {
            $this->form->fill(['email' => ModoDemo::CORREO, 'password' => ModoDemo::CONTRASENA]);
        }
    }

    /**
     * El enlace de «¿La olvidaste?» aparece solo si se puede mandar el correo.
     */
    protected function getPasswordFormComponent(): Component
    {
        $campo = parent::getPasswordFormComponent();

        return PedirRecuperacion::disponible() || ! $campo instanceof TextInput ? $campo : $campo->hint(null);
    }
}
