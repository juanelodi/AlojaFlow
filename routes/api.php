<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Http\Controllers\CompartirPropiedadController;
use App\Http\Middleware\AutenticarConexion;
use App\Http\Middleware\ModuloActivo;
use Illuminate\Support\Facades\Route;

// Lo que lee una subordinada (D-073; docs/especificacion/subordinacion-alojaflow.md). Sin sesión: la identifica su token.
Route::prefix('subordinacion/v1')->middleware([ModuloActivo::class.':subordinacion', 'throttle:240,1', AutenticarConexion::class])->group(function (): void {
    Route::get('/estado', [CompartirPropiedadController::class, 'estado'])->name('subordinacion.estado');
    Route::get('/datos/{coleccion}', [CompartirPropiedadController::class, 'datos'])->name('subordinacion.datos');
});
