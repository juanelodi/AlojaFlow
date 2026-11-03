<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Http\Controllers\ActualizacionController;
use App\Http\Controllers\CodigoFuenteController;
use Illuminate\Support\Facades\Route;

// La aplicación vive en el panel de Filament (raíz «/»). Aquí solo van las rutas que no son del panel.

// Código fuente de la versión instalada, también sin iniciar sesión (AGPL; regla 8).
Route::get('/codigo-fuente', CodigoFuenteController::class)
    ->middleware('throttle:10,1')
    ->name('codigo-fuente');

// Fases de una actualización desde el panel (instalacion-actualizaciones.md). Firmadas y solo
// para administradores (lo comprueba el controlador).
Route::middleware(['auth', 'signed'])->group(function (): void {
    Route::get('/actualizaciones/{actualizacion}/aplicar', [ActualizacionController::class, 'aplicar'])->name('actualizaciones.aplicar');
    Route::get('/actualizaciones/{actualizacion}/finalizar', [ActualizacionController::class, 'finalizar'])->name('actualizaciones.finalizar');
});
Route::middleware('auth')->get('/actualizaciones/{actualizacion}/copia-base-datos', [ActualizacionController::class, 'copiaBaseDatos'])
    ->name('actualizaciones.copia-base-datos');
