<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use App\Models\Principal;
use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use LogicException;

/**
 * Lee de otra instalación de AlojaFlow. Solo hace GET, y el token va en una cabecera: nunca en la dirección, para que no acabe en
 * registros ni en mensajes de error (regla 13).
 */
class ClienteRemoto
{
    private const RUTA = 'api/subordinacion/v1/';

    private const BYTES_MAXIMOS_RESPUESTA = 52428800;

    public function __construct(
        private readonly Principal $principal,
        private readonly Factory $http,
        private readonly ValidadorDestino $validador,
    ) {
    }

    /**
     * @return array{api: int, version: string, revision: string, propiedad: array<string, mixed>}
     */
    public function estado(): array
    {
        $datos = $this->pedir('estado', [], 'probar');
        if (! is_int($datos['api'] ?? null) || ! is_string($datos['revision'] ?? null) || ! is_array($datos['propiedad'] ?? null)) {
            throw new ErrorSincronizacion(CodigoError::PrincipalSinCompartir, 'probar');
        }
        if ($datos['api'] !== Exportador::VERSION_API) {
            throw new ErrorSincronizacion(CodigoError::VersionNoAdmitida, 'probar', (string) ($datos['version'] ?? ''));
        }
        $datos['version'] = (string) ($datos['version'] ?? '');

        /** @var array{api: int, version: string, revision: string, propiedad: array<string, mixed>} $datos */
        return $datos;
    }

    /**
     * @return array{filas: list<array<string, mixed>>, siguiente: int|null}
     */
    public function pagina(string $coleccion, int $despues): array
    {
        $datos = $this->pedir('datos/'.$coleccion, ['despues' => $despues], 'descargar');
        if (! is_array($datos['filas'] ?? null)) {
            throw new ErrorSincronizacion(CodigoError::PrincipalSinCompartir, 'descargar');
        }
        $siguiente = $datos['siguiente'] ?? null;

        /** @var list<array<string, mixed>> $filas */
        $filas = array_values(array_filter($datos['filas'], 'is_array'));

        return ['filas' => $filas, 'siguiente' => is_int($siguiente) && $siguiente > $despues ? $siguiente : null];
    }

    /**
     * @param  array<string, int>  $consulta
     * @return array<string, mixed>
     */
    private function pedir(string $ruta, array $consulta, string $paso): array
    {
        $redLocal = (bool) config('alojaflow.subordinacion.red_local') && $this->principal->permitir_red_local;
        $destino = $this->validador->validar($this->principal->url_base, $redLocal);

        $excedido = false;
        $opciones = [
            'allow_redirects' => false,
            'progress' => function (int|float $total, int|float $descargado) use (&$excedido): void {
                if ($total > self::BYTES_MAXIMOS_RESPUESTA || $descargado > self::BYTES_MAXIMOS_RESPUESTA) {
                    $excedido = true;
                    throw new LogicException('limite');
                }
            },
        ];
        if (defined('CURLOPT_RESOLVE')) {
            // Fija la IP ya validada para que no cambie entre la validación y la conexión.
            $opciones['curl'] = [CURLOPT_RESOLVE => [$destino['host'].':'.$destino['puerto'].':'.$destino['ip']]];
        }

        try {
            $respuesta = $this->http
                ->withUserAgent(sprintf((string) config('alojaflow.subordinacion.agente'), (string) config('alojaflow.version')))
                ->withToken((string) $this->principal->contrasena)
                ->acceptJson()
                ->connectTimeout((int) config('alojaflow.subordinacion.tiempo_conexion'))
                ->timeout((int) config('alojaflow.subordinacion.tiempo_peticion'))
                ->withOptions($opciones)
                ->get($this->principal->url_base.self::RUTA.$ruta, $consulta);
        } catch (ConnectionException) {
            throw new ErrorSincronizacion(CodigoError::Conexion, $paso);
        } catch (LogicException $e) {
            if ($excedido) {
                throw new ErrorSincronizacion(CodigoError::TamanoExcedido, $paso);
            }
            throw $e;
        }

        $estado = $respuesta->status();
        if ($estado === 401 || $estado === 403) {
            throw new ErrorSincronizacion(CodigoError::ConexionRechazada, $paso);
        }
        if ($estado === 429 || $estado >= 500) {
            throw new ErrorSincronizacion(CodigoError::Conexion, $paso, 'HTTP '.$estado);
        }
        $datos = $estado === 200 ? $respuesta->json() : null;
        if (! is_array($datos)) {
            // Una versión anterior, un módulo apagado o una dirección que no es de AlojaFlow.
            throw new ErrorSincronizacion(CodigoError::PrincipalSinCompartir, $paso, 'HTTP '.$estado);
        }

        return $datos;
    }
}
