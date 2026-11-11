<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Support\Ajustes;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Habla con el receptor de mejora continua (protocolo v1, docs/especificacion/mejora-continua.md §4). La
 * instalación se registra con un UUID al azar y recibe una ficha, que se guarda cifrada. Nunca lanza
 * nada que no sea ErrorReceptor: quien llama decide si reintenta.
 */
final class ClienteReceptor
{
    public const AJUSTE_UUID = 'mejora.uuid';

    public const AJUSTE_FICHA = 'mejora.ficha';

    public function registrado(): bool
    {
        return is_string(Ajustes::obtener(self::AJUSTE_UUID)) && Ajustes::secreto(self::AJUSTE_FICHA) !== null;
    }

    public function uuid(): ?string
    {
        $uuid = Ajustes::obtener(self::AJUSTE_UUID);

        return is_string($uuid) ? $uuid : null;
    }

    /**
     * Lo que va en cada envío de una instancia alojada (D-168).
     *
     * @return array<string, array{codigo: string}>
     */
    private function marca(): array
    {
        $alojada = Permiso::alojada();

        return $alojada === null ? [] : ['alojada' => $alojada];
    }

    /**
     * @throws ErrorReceptor
     */
    public function asegurarRegistro(): void
    {
        if ($this->registrado()) {
            return;
        }
        // Si el UUID ya existe allá (una copia de esta instalación se registró antes), se prueba con otro.
        for ($intento = 0; $intento < 2; $intento++) {
            $uuid = (string) Str::uuid();
            $respuesta = $this->enviar(fn (PendingRequest $http): Response => $http->post('instalaciones', ['uuid' => $uuid, 'version' => (string) config('alojaflow.version')] + $this->marca()));
            if ($respuesta->status() === 409) {
                continue;
            }
            $ficha = $this->datos($respuesta)['ficha'] ?? null;
            if (! is_string($ficha) || $ficha === '') {
                throw new ErrorReceptor('registro', $respuesta->status());
            }
            Ajustes::guardar(self::AJUSTE_UUID, $uuid);
            Ajustes::guardarSecreto(self::AJUSTE_FICHA, $ficha);

            return;
        }
        throw new ErrorReceptor('registro', 409);
    }

    /**
     * @param  array<string, mixed>  $latido
     * @return array<string, mixed> la respuesta del receptor
     *
     * @throws ErrorReceptor
     */
    public function latido(array $latido): array
    {
        return $this->datos($this->autenticada(fn (PendingRequest $http): Response => $http->post('latidos', $latido)));
    }

    /**
     * @param  list<array<string, mixed>>  $errores
     *
     * @throws ErrorReceptor
     */
    public function errores(array $errores): void
    {
        $this->datos($this->autenticada(fn (PendingRequest $http): Response => $http->post('errores', ['errores' => $errores] + $this->marca())));
    }

    /**
     * @param  array<string, mixed>  $reporte
     * @return array<string, mixed> folio, estado…
     *
     * @throws ErrorReceptor
     */
    public function crearReporte(array $reporte): array
    {
        return $this->datos($this->autenticada(fn (PendingRequest $http): Response => $http->post('reportes', $reporte + $this->marca())));
    }

    /**
     * @throws ErrorReceptor
     */
    public function subirImagen(string $folio, string $contenido, string $nombre, int $orden): void
    {
        $this->datos($this->autenticada(fn (PendingRequest $http): Response => $http
            ->attach('imagen', $contenido, $nombre)
            ->post('reportes/'.rawurlencode($folio).'/imagenes', ['orden' => (string) $orden])));
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws ErrorReceptor
     */
    public function reportes(?string $desde = null): array
    {
        $datos = $this->datos($this->autenticada(fn (PendingRequest $http): Response => $http->get('reportes', array_filter(['desde' => $desde]))));

        return array_values(array_filter((array) ($datos['reportes'] ?? []), 'is_array'));
    }

    /**
     * «Olvidar mi instalación»: el receptor borra todo lo suyo y aquí se olvida la identidad.
     *
     * @throws ErrorReceptor
     */
    public function olvidar(): void
    {
        if ($this->registrado()) {
            $respuesta = $this->autenticada(fn (PendingRequest $http): Response => $http->delete('instalaciones/yo'));
            if (! $respuesta->successful() && $respuesta->status() !== 401) {
                throw new ErrorReceptor('olvidar', $respuesta->status());
            }
        }
        $this->olvidarIdentidad();
    }

    public function olvidarIdentidad(): void
    {
        Ajustes::borrar(self::AJUSTE_UUID);
        Ajustes::borrar(self::AJUSTE_FICHA);
    }

    /**
     * @param  callable(PendingRequest): Response  $llamada
     *
     * @throws ErrorReceptor
     */
    private function autenticada(callable $llamada): Response
    {
        $this->asegurarRegistro();
        $respuesta = $this->enviar(fn (PendingRequest $http): Response => $llamada($http
            ->withToken((string) Ajustes::secreto(self::AJUSTE_FICHA))
            ->withHeaders(['X-AlojaFlow-Instalacion' => (string) $this->uuid()])));
        if ($respuesta->status() === 401) {
            // La ficha ya no vale (la instalación se olvidó allá, o se perdió): la próxima vez se registra de nuevo.
            $this->olvidarIdentidad();
            throw new ErrorReceptor('credenciales', 401);
        }

        return $respuesta;
    }

    /**
     * @param  callable(PendingRequest): Response  $llamada
     *
     * @throws ErrorReceptor
     */
    private function enviar(callable $llamada): Response
    {
        $url = rtrim((string) config('alojaflow.mejora_continua.url'), '/');
        $esquema = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! Permiso::hayReceptor() || ($esquema !== 'https' && ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true))) {
            throw new ErrorReceptor('sin_receptor', 0);
        }
        $http = Http::baseUrl($url.'/')
            ->acceptJson()
            ->withUserAgent(sprintf((string) config('alojaflow.mejora_continua.agente'), (string) config('alojaflow.version')))
            ->connectTimeout((int) config('alojaflow.mejora_continua.tiempo_conexion'))
            ->timeout((int) config('alojaflow.mejora_continua.tiempo_peticion'))
            ->withoutRedirecting();
        try {
            return $llamada($http);
        } catch (ConnectionException) {
            throw new ErrorReceptor('sin_conexion', 0);
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ErrorReceptor
     */
    private function datos(Response $respuesta): array
    {
        if ($respuesta->status() === 204) {
            return [];
        }
        if (! $respuesta->successful()) {
            $codigo = $respuesta->json('error');
            throw new ErrorReceptor(is_string($codigo) ? Str::limit($codigo, 40, '') : 'http', $respuesta->status());
        }
        $datos = $respuesta->json();

        return is_array($datos) ? $datos : [];
    }
}
