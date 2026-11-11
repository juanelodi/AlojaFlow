<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Filament\Pages\CrearContrasena;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien todavía no tiene contraseña propia (D-164) va primero a crearla. Lo de la cuenta (salir, la verificación en
 * dos pasos que exija el panel) sigue disponible.
 */
class ContrasenaPorCrear
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if ($usuario instanceof User && $usuario->debe_crear_contrasena
            && ! $request->routeIs(CrearContrasena::getRouteName(), 'filament.app.auth.*')) {
            return redirect()->to(CrearContrasena::getUrl());
        }

        return $next($request);
    }
}
