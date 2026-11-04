<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Programador\TareasPorVisita;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Da su turno a las tareas programadas después de responder, si no hay cron (D-039).
 */
class ProgramadorPorVisitas
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! app()->runningUnitTests() && ! app()->isDownForMaintenance()) {
            TareasPorVisita::atender();
        }
    }
}
