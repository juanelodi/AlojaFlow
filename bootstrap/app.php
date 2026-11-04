<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Http\Middleware\ProgramadorPorVisitas;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Las dos fases de una actualización corren con la aplicación en mantenimiento. La cookie
        // que deja pasar al administrador durante el mantenimiento va sin cifrar: la lee
        // storage/framework/maintenance.php antes de arrancar Laravel (y ya va firmada).
        $middleware->preventRequestsDuringMaintenance(except: ['actualizaciones/*/aplicar', 'actualizaciones/*/finalizar']);
        $middleware->encryptCookies(except: ['laravel_maintenance']);
        $middleware->append(ProgramadorPorVisitas::class);
        // Sin sesión, al inicio de sesión del panel.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
