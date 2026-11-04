<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Peticiones a direcciones que escribió un usuario (avisos a otros sistemas, calendarios iCal).
 * Solo a redes públicas, con la IP ya comprobada fija en la conexión: así una dirección no puede
 * apuntar a la red interna del servidor, ni cambiar de IP después de la revisión.
 */
final class ClienteExterno
{
    private const REDIRECCIONES_MAXIMAS = 3;

    public function __construct(private readonly ValidadorDestino $validador)
    {
    }

    /**
     * @throws RuntimeException si la dirección no se permite
     */
    public function comprobar(string $url, bool $permitirHttp = false): void
    {
        $this->peticion($url, $permitirHttp);
    }

    public function peticion(string $url, bool $permitirHttp = false): PendingRequest
    {
        $destino = $this->validador->validarEnlace($url, $permitirHttp);

        return Http::withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$destino['host'].':'.$destino['puerto'].':'.$destino['ip']]]])
            ->timeout(20)->connectTimeout(10);
    }

    /**
     * Descarga una dirección, siguiendo pocas redirecciones y comprobando cada una.
     */
    public function descargar(string $url, bool $permitirHttp = false): Response
    {
        for ($i = 0; $i <= self::REDIRECCIONES_MAXIMAS; $i++) {
            $respuesta = $this->peticion($url, $permitirHttp)->get($url);
            if (! $respuesta->redirect()) {
                return $respuesta;
            }
            $siguiente = (string) $respuesta->header('Location');
            if ($siguiente === '') {
                return $respuesta;
            }
            $url = str_starts_with($siguiente, '/') ? (string) preg_replace('#^(https?://[^/]+).*$#i', '$1', $url).$siguiente : $siguiente;
        }

        throw new RuntimeException(__('mensajes.error_redirecciones'));
    }
}
