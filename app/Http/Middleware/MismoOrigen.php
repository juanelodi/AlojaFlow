<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El motor de reservas no usa token CSRF, porque dentro de un iframe el navegador puede no guardar la
 * cookie de sesión. El formulario sale de esta misma dirección también dentro del iframe, así que un
 * envío con otro origen viene de una página ajena que usa a sus visitantes para reservar.
 */
class MismoOrigen
{
    public function handle(Request $request, Closure $next): Response
    {
        $origen = $request->headers->get('Origin');
        // También el de la instalación: detrás de un proxy, el Host que llega aquí puede ser otro.
        $propios = [$request->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)];
        abort_if($origen !== null && ! in_array(parse_url($origen, PHP_URL_HOST), $propios, true), 403);

        return $next($request);
    }
}
