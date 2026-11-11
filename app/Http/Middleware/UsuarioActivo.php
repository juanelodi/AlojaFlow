<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Seguridad\Acceso;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Para las rutas con sesión que están fuera del panel: al panel ya no entra un usuario desactivado,
 * y aquí tampoco debe llegar con una sesión que abrió antes.
 */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Acceso::usuarioActual()?->puedeEntrar() !== true) {
            Auth::logout();
            abort(403);
        }

        return $next($request);
    }
}
