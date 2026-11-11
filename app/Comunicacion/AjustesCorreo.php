<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Demo\ModoDemo;
use App\Instalacion\Instancia;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Mailer as MailerLaravel;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

/**
 * El correo de salida se configura desde el panel, sin tocar archivos (D-045). Sin configurar, los
 * mensajes quedan listos para copiarlos a mano. En el servicio alojado (D-165), el correo del .env es el de la
 * plataforma y cuenta como configurado; el servidor que escriba el alojamiento debe estar en una red pública.
 */
final class AjustesCorreo
{
    public const MAILER = 'alojaflow';

    /** Los transportes que no mandan nada: con ellos la plataforma no tiene correo. */
    private const SIN_ENVIO = ['log', 'array', ''];

    public function __construct(private readonly ValidadorDestino $validador)
    {
    }

    public function configurado(): bool
    {
        return ModoDemo::activo() || $this->propio() || $this->dePlataforma();
    }

    /**
     * El servidor SMTP que escribió el alojamiento en Comunicación → Correo.
     */
    public function propio(): bool
    {
        return filled(Ajustes::obtener('correo.host'));
    }

    /**
     * En una instancia, el proveedor de la plataforma que puso AlojaFlow App en el .env. En una instalación propia,
     * el .env no cuenta: el correo se configura en el panel.
     */
    public function dePlataforma(): bool
    {
        return Instancia::activa() && ! in_array((string) config('mail.default'), self::SIN_ENVIO, true);
    }

    /**
     * Con el correo de la plataforma sale con su remitente: el proveedor no deja pasar el de otro dominio. Las
     * respuestas van al correo de la propiedad (Responder a).
     */
    public function remitente(): string
    {
        if (! $this->propio() && $this->dePlataforma()) {
            return (string) config('mail.from.address');
        }

        return (string) Ajustes::obtener('correo.remitente_correo', config('mail.from.address'));
    }

    public function nombreRemitente(): string
    {
        return (string) Ajustes::obtener('correo.remitente_nombre', 'AlojaFlow');
    }

    public function mailer(): Mailer
    {
        return Mail::mailer($this->prepararMailer());
    }

    /**
     * Deja listo el transporte que toca y dice cuál es.
     *
     * @throws RuntimeException si, en una instancia, el servidor del alojamiento ya no está en una red pública
     */
    public function prepararMailer(): string
    {
        if (ModoDemo::activo()) {
            return 'log';
        }
        if (! $this->propio() && $this->dePlataforma()) {
            return (string) config('mail.default');
        }
        $host = (string) Ajustes::obtener('correo.host');
        // En una instancia se conecta a la IP ya revisada: el nombre no puede cambiar a la red interna entre la
        // revisión y la conexión. El certificado se sigue comprobando con el nombre.
        try {
            $conexion = Instancia::activa() ? $this->validador->validarServidor($host) : $host;
        } catch (ErrorSincronizacion) {
            throw new RuntimeException(__('mensajes.correo.red_publica', ['host' => $host]));
        }
        $cifrado = (string) Ajustes::obtener('correo.cifrado', 'tls');
        config(['mail.mailers.'.self::MAILER => [
            'transport' => 'smtp',
            'host' => $conexion,
            'port' => (int) Ajustes::obtener('correo.puerto', 587),
            // Laravel 13 ya no lee «encryption». Sin require_tls, quien esté en el camino puede quitar
            // STARTTLS de la respuesta y la contraseña viajaría en claro.
            'scheme' => $cifrado === 'ssl' ? 'smtps' : 'smtp',
            'auto_tls' => $cifrado !== 'ninguno',
            'require_tls' => $cifrado === 'tls',
            'username' => (string) Ajustes::obtener('correo.usuario'),
            'password' => Ajustes::secreto('correo.contrasena'),
            'timeout' => 20,
        ]]);
        Mail::purge(self::MAILER);
        if ($conexion !== $host) {
            $mailer = Mail::mailer(self::MAILER);
            $transporte = $mailer instanceof MailerLaravel ? $mailer->getSymfonyTransport() : null;
            $flujo = $transporte instanceof EsmtpTransport ? $transporte->getStream() : null;
            if ($flujo instanceof SocketStream) {
                $flujo->setStreamOptions(array_replace_recursive($flujo->getStreamOptions(), ['ssl' => ['peer_name' => $host]]));
            }
        }

        return self::MAILER;
    }
}
