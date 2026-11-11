<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Comunicacion\AjustesCorreo;
use App\Demo\ModoDemo;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use RuntimeException;

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
        // El correo se configura desde el panel: la notificación sale con esa cuenta, no con la de .env (en el servicio
        // alojado, con la de la plataforma si el alojamiento no puso la suya).
        $correo = app(AjustesCorreo::class);
        try {
            $mailer = $correo->prepararMailer();
        } catch (RuntimeException $e) {
            // En el servicio alojado, el servidor del alojamiento dejó de estar en una red pública: no se manda nada,
            // pero quien lo pide ve la misma respuesta de siempre.
            report($e);
            $this->getSentNotification(Password::RESET_LINK_SENT)?->send();

            return;
        }
        config([
            'mail.default' => $mailer,
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
