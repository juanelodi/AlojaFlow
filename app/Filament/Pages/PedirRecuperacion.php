<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\AjustesCorreo;
use App\Demo\ModoDemo;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * Pedir el enlace para cambiar la contraseña olvidada. Solo tiene sentido con correo configurado: sin
 * él, quien olvidó la suya la cambia por consola (`alojaflow:crear-administrador`).
 */
class PedirRecuperacion extends RequestPasswordReset
{
    public static function disponible(): bool
    {
        return ! ModoDemo::activo() && app(AjustesCorreo::class)->configurado();
    }

    public function mount(): void
    {
        abort_unless(self::disponible(), 404);

        parent::mount();
    }

    public function request(): void
    {
        // El correo se configura desde el panel: la notificación sale con esa cuenta, no con la de .env.
        $correo = app(AjustesCorreo::class);
        $correo->mailer();
        config([
            'mail.default' => AjustesCorreo::MAILER,
            'mail.from' => ['address' => $correo->remitente(), 'name' => $correo->nombreRemitente()],
        ]);

        // El enlace lleva el token: con la dirección de la instalación, nunca con la cabecera Host que mande quien lo pide.
        URL::forceRootUrl((string) config('app.url'));

        parent::request();
    }

    /**
     * La misma respuesta exista o no la cuenta: esta página no debe servir para averiguar quién tiene una.
     */
    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->getSentNotification(Password::RESET_LINK_SENT);
    }
}
