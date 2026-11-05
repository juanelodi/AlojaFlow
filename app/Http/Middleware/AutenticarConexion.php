<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ConexionCompartida;
use App\Sincronizacion\AlojaFlow\GestorConexiones;
use Closure;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quién lee: el token de una conexión vigente, y de una propiedad que sigue activa e independiente.
 */
class AutenticarConexion
{
    private const ATRIBUTO = 'conexion_compartida';

    public function __construct(private readonly GestorConexiones $conexiones)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        $conexion = $token === '' ? null : $this->conexiones->vigente($token);
        if ($conexion === null) {
            return response()->json(['error' => 'no_autorizado'], 401);
        }
        // No es un código malo: la subordinada reintenta y, si sigue así, avisa. Con un 401 se quedaría pausada aunque vuelva a activarse.
        if (! $conexion->propiedad->activa || ! $conexion->propiedad->esIndependiente()) {
            return response()->json(['error' => 'no_disponible'], 404);
        }
        // Una vez por minuto basta: una sincronización hace decenas de peticiones.
        if ($conexion->ultimo_uso_en === null || $conexion->ultimo_uso_en->lt(now()->subMinute())) {
            ConexionCompartida::query()->whereKey($conexion->id)->toBase()->update(['ultimo_uso_en' => now('UTC')]);
        }
        $request->attributes->set(self::ATRIBUTO, $conexion);

        return $next($request);
    }

    public static function de(Request $request): ConexionCompartida
    {
        $conexion = $request->attributes->get(self::ATRIBUTO);
        if (! $conexion instanceof ConexionCompartida) {
            throw new LogicException('La ruta no pasó por AutenticarConexion.');
        }

        return $conexion;
    }
}
