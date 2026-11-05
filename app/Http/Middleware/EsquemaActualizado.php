<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actualizacion\EsquemaAlDia;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Antes de atender, la base al día (D-089). Una visita continúa donde iba; un formulario va al inicio, porque ese envío no se procesó. En una
 * actualización normal la aplicación está en mantenimiento y de esto se encarga el actualizador.
 */
class EsquemaActualizado
{
    public function __construct(private readonly EsquemaAlDia $esquema)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() || app()->isDownForMaintenance() || ! $this->esquema->hayQueRevisar()) {
            return $next($request);
        }
        try {
            // Esta petición arrancó con las cachés de la versión anterior: se migra en la siguiente, que ya arranca limpia.
            if ($this->esquema->quitarCachesViejas()) {
                return redirect($request->isMethodSafe() ? $request->fullUrl() : '/');
            }
            $this->esquema->ponerAlDia();
        } catch (Throwable $e) {
            report($e);

            return response()->view('errors.layout', ['clave' => 'esquema'], 503);
        }

        return $request->isMethodSafe() ? $next($request) : redirect('/');
    }
}
