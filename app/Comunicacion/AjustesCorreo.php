<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Demo\ModoDemo;
use App\Support\Ajustes;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

/**
 * El correo de salida se configura desde el panel, sin tocar archivos (D-045). Sin configurar, los
 * mensajes quedan listos para copiarlos a mano.
 */
final class AjustesCorreo
{
    private const MAILER = 'alojaflow';

    public function configurado(): bool
    {
        return ModoDemo::activo() || filled(Ajustes::obtener('correo.host'));
    }

    public function remitente(): string
    {
        return (string) Ajustes::obtener('correo.remitente_correo', config('mail.from.address'));
    }

    public function nombreRemitente(): string
    {
        return (string) Ajustes::obtener('correo.remitente_nombre', 'AlojaFlow');
    }

    public function mailer(): Mailer
    {
        if (ModoDemo::activo()) {
            return Mail::mailer('log');
        }
        $cifrado = (string) Ajustes::obtener('correo.cifrado', 'tls');
        config(['mail.mailers.'.self::MAILER => [
            'transport' => 'smtp',
            'host' => (string) Ajustes::obtener('correo.host'),
            'port' => (int) Ajustes::obtener('correo.puerto', 587),
            'encryption' => $cifrado === 'ninguno' ? null : $cifrado,
            'username' => (string) Ajustes::obtener('correo.usuario'),
            'password' => Ajustes::secreto('correo.contrasena'),
            'timeout' => 20,
        ]]);
        Mail::purge(self::MAILER);

        return Mail::mailer(self::MAILER);
    }
}
