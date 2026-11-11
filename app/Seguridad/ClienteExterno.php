<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Seguridad;

use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Peticiones a direcciones que escribió un usuario (avisos a otros sistemas, calendarios iCal).
 * Solo a redes públicas, con la IP ya comprobada fija en la conexión: así una dirección no puede
 * apuntar a la red interna del servidor, ni cambiar de IP después de la revisión.
 */
final class ClienteExterno
{
    private const REDIRECCIONES_MAXIMAS = 3;

    private const BYTES_MAXIMOS = 5242880;

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

        return Http::withOptions(['allow_redirects' => false, 'decode_content' => false, 'curl' => [CURLOPT_RESOLVE => [$destino['host'].':'.$destino['puerto'].':'.$destino['ip']]]])
            ->timeout(20)->connectTimeout(10);
    }

    /**
     * Descarga una dirección, siguiendo pocas redirecciones y comprobando cada una. Se corta al pasar
     * del tope: lo que llega se guarda en memoria.
     */
    public function descargar(string $url, bool $permitirHttp = false, int $bytesMaximos = self::BYTES_MAXIMOS): Response
    {
        for ($i = 0; $i <= self::REDIRECCIONES_MAXIMAS; $i++) {
            $excedido = false;
            try {
                $respuesta = $this->peticion($url, $permitirHttp)->withHeaders(['Accept-Encoding' => 'identity'])->withOptions([
                    'progress' => function (int|float $total, int|float $descargado) use ($bytesMaximos, &$excedido): void {
                        if ($total > $bytesMaximos || $descargado > $bytesMaximos) {
                            $excedido = true;
                            throw new LogicException('limite');
                        }
                    },
                ])->get($url);
            } catch (Throwable $e) {
                throw $excedido ? new RuntimeException(__('mensajes.error_tamano'), 0, $e) : $e;
            }
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
