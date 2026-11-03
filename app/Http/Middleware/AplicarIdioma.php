<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cada usuario ve la interfaz en su idioma (regla 7) y registra su último acceso.
 */
class AplicarIdioma
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if ($usuario instanceof User) {
            $idioma = in_array($usuario->idioma, (array) config('alojaflow.idiomas'), true) ? $usuario->idioma : (string) config('app.locale');
            app()->setLocale($idioma);
            Carbon::setLocale($idioma);

            if ($usuario->ultimo_acceso_en === null || $usuario->ultimo_acceso_en->lt(now()->subMinutes(5))) {
                $usuario->forceFill(['ultimo_acceso_en' => now()])->saveQuietly();
            }
        }

        return $next($request);
    }
}
