<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Http\Controllers\CodigoFuenteController;
use Illuminate\Support\Facades\Route;

// La aplicación vive en el panel de Filament (raíz «/»). Aquí solo van las rutas públicas.

// Código fuente de la versión instalada, también sin iniciar sesión (AGPL; regla 8).
Route::get('/codigo-fuente', CodigoFuenteController::class)
    ->middleware('throttle:10,1')
    ->name('codigo-fuente');
