<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Seguridad\Dispositivos;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin sesión, pero con un dispositivo recordado: vuelve a entrar. Va después de StartSession.
 */
class RecordarDispositivo
{
    public function __construct(private readonly Dispositivos $dispositivos)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $cookie = $request->cookie(Dispositivos::COOKIE);
        if (is_string($cookie) && $cookie !== '' && Auth::guest()) {
            $usuario = $this->dispositivos->usar($cookie, $request->userAgent(), $request->ip());
            if ($usuario !== null) {
                Auth::login($usuario);
            } else {
                Cookie::queue(Cookie::forget(Dispositivos::COOKIE));
            }
        }

        return $next($request);
    }
}
