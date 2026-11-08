<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Http\Middleware\CabecerasSeguridad;
use App\Http\Middleware\ContarPantallas;
use App\Http\Middleware\EsquemaActualizado;
use App\Http\Middleware\ProgramadorPorVisitas;
use App\Http\Middleware\RecordarDispositivo;
use App\MejoraContinua\RegistroErrores;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

// Quien edita .env a mano (en un hosting compartido es lo normal) espera que cuente sin saber de cachés.
$configuracionGuardada = __DIR__.'/cache/config.php';
if (is_file($configuracionGuardada) && @filemtime(dirname(__DIR__).'/.env') > filemtime($configuracionGuardada)) {
    @unlink($configuracionGuardada);
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Primero que todo: también las páginas de error y la de mantenimiento llevan las cabeceras.
        $middleware->prepend(CabecerasSeguridad::class);
        // Las dos fases de una actualización corren con la aplicación en mantenimiento. La cookie
        // que deja pasar al administrador durante el mantenimiento va sin cifrar: la lee
        // storage/framework/maintenance.php antes de arrancar Laravel (y ya va firmada).
        $middleware->preventRequestsDuringMaintenance(except: ['actualizaciones/*/aplicar', 'actualizaciones/*/finalizar']);
        $middleware->encryptCookies(except: ['laravel_maintenance']);
        $middleware->append(EsquemaActualizado::class);
        $middleware->append(ProgramadorPorVisitas::class);
        // Mejora continua (D-150): cuántas veces se abre cada pantalla, solo con permiso.
        $middleware->append(ContarPantallas::class);
        $middleware->web(append: [RecordarDispositivo::class]);
        // El motor de reservas no da permisos por sesión y debe funcionar dentro de un iframe; los
        // avisos de pago llegan de la pasarela y se comprueban con su firma.
        $middleware->validateCsrfTokens(except: ['reservar/*', 'avisos-pago/*']);
        // Sin sesión, al inicio de sesión del panel.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mejora continua (D-150): el error saneado se junta para mandarlo, solo con permiso. Sigue al registro.
        $exceptions->report(fn (\Throwable $error) => RegistroErrores::anotar($error));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
