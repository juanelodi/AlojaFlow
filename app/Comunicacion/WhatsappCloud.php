<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Support\Ajustes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SensitiveParameter;

/**
 * WhatsApp por la API en la nube de Meta (D-160), sin SDK. Cada propiedad pone su número (su identificador en Meta) y un token
 * permanente. Un mensaje que manda el alojamiento sin que el huésped haya escrito antes solo puede ser una plantilla aprobada en
 * WhatsApp Manager: eso es lo que mandan las automatizaciones, con las variables en el orden de la plantilla.
 */
final class WhatsappCloud
{
    public const API = 'https://graph.facebook.com/';

    public const VERSION = 'v23.0';

    public function __construct(private readonly int $propiedadId)
    {
    }

    public function configurado(): bool
    {
        return $this->numeroId() !== null && filled(Ajustes::obtener($this->clave('token')));
    }

    public function numeroId(): ?string
    {
        $id = Ajustes::obtener($this->clave('numero_id'));

        return is_string($id) && preg_match('/^[0-9]{5,30}$/', $id) === 1 ? $id : null;
    }

    public function version(): string
    {
        $version = Ajustes::obtener($this->clave('version'));

        return is_string($version) && preg_match('/^v[0-9]{2}\.[0-9]$/', $version) === 1 ? $version : self::VERSION;
    }

    /**
     * Vacío el token, se conserva el que hay. Para quitarlo todo, quitar().
     */
    public function guardar(?string $numeroId, #[SensitiveParameter] ?string $token, ?string $version): void
    {
        Ajustes::guardar($this->clave('numero_id'), filled($numeroId) ? trim((string) $numeroId) : null);
        Ajustes::guardar($this->clave('version'), filled($version) ? trim((string) $version) : null);
        if (filled($token)) {
            Ajustes::guardarSecreto($this->clave('token'), trim((string) $token));
        }
    }

    public function quitar(): void
    {
        Ajustes::guardar($this->clave('numero_id'), null);
        Ajustes::guardar($this->clave('version'), null);
        Ajustes::guardarSecreto($this->clave('token'), null);
    }

    /**
     * Manda una plantilla aprobada. Devuelve el identificador del mensaje en Meta; si Meta lo rechaza, lanza la razón que da.
     *
     * @param  list<string>  $parametros  las variables del cuerpo, en orden: {{1}}, {{2}}…
     */
    public function enviarPlantilla(string $telefono, string $plantilla, string $idioma, array $parametros): string
    {
        $numero = $this->numeroId();
        $token = Ajustes::secreto($this->clave('token'));
        if ($numero === null || blank($token)) {
            throw new RuntimeException(__('mensajes.whatsapp.sin_configurar'));
        }
        $plantillaMeta = ['name' => $plantilla, 'language' => ['code' => $idioma]];
        if ($parametros !== []) {
            // Meta rechaza un parámetro vacío: se manda un guion.
            $plantillaMeta['components'] = [['type' => 'body', 'parameters' => array_map(fn (string $texto): array => ['type' => 'text', 'text' => trim($texto) === '' ? '-' : mb_substr($texto, 0, 1000)], $parametros)]];
        }
        try {
            $respuesta = Http::baseUrl(self::API.$this->version().'/')->withToken((string) $token)->acceptJson()->asJson()->timeout(20)->connectTimeout(10)
                ->post($numero.'/messages', ['messaging_product' => 'whatsapp', 'to' => preg_replace('/\D+/', '', $telefono), 'type' => 'template', 'template' => $plantillaMeta]);
        } catch (ConnectionException) {
            throw new RuntimeException(__('mensajes.whatsapp.sin_respuesta'));
        }
        $id = $respuesta->json('messages.0.id');
        if (! $respuesta->successful() || ! is_string($id)) {
            // Lo que dice Meta («Template name does not exist in the translation»…), nunca el token.
            throw new RuntimeException(__('mensajes.whatsapp.rechazado', ['motivo' => mb_substr((string) ($respuesta->json('error.error_data.details') ?? $respuesta->json('error.message') ?? $respuesta->status()), 0, 300)]));
        }

        return $id;
    }

    private function clave(string $nombre): string
    {
        return 'whatsapp.'.$this->propiedadId.'.'.$nombre;
    }
}
