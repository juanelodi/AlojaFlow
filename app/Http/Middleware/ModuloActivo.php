<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modulos\Modulo;
use App\Modulos\Modulos;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las páginas públicas de un módulo apagado no existen: `->middleware(ModuloActivo::class.':motor')`.
 */
class ModuloActivo
{
    public function handle(Request $request, Closure $next, string $modulo): Response
    {
        abort_unless(Modulos::activo(Modulo::from($modulo)), 404);

        return $next($request);
    }
}
