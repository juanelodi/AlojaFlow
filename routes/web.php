<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Http\Controllers\ActualizacionController;
use App\Http\Controllers\ArchivoController;
use App\Http\Controllers\CodigoFuenteController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\MotorReservasController;
use Illuminate\Support\Facades\Route;

// La aplicación vive en el panel de Filament (raíz «/»). Aquí solo van las rutas que no son del panel.

// Código fuente de la versión instalada, también sin iniciar sesión (AGPL; regla 8).
Route::get('/codigo-fuente', CodigoFuenteController::class)
    ->middleware('throttle:10,1')
    ->name('codigo-fuente');

// Calendario de una unidad para los portales: lo identifica un token, y solo lleva fechas.
Route::get('/ical/{token}.ics', IcalController::class)->where('token', '[0-9a-f]{40}')->middleware('throttle:60,1')->name('ical.exportar');

// Archivos privados: solo con sesión y según el permiso de su clase.
Route::get('/archivos/{archivo}', ArchivoController::class)->middleware(['auth', 'throttle:120,1'])->name('archivos.ver');

// Motor de reservas: páginas públicas del alojamiento (D-042).
Route::prefix('reservar/{codigo}')->name('motor.')->middleware('throttle:60,1')->group(function (): void {
    Route::get('/', [MotorReservasController::class, 'buscar'])->name('buscar');
    Route::get('/datos', [MotorReservasController::class, 'datos'])->name('datos');
    Route::post('/', [MotorReservasController::class, 'reservar'])->middleware('throttle:10,1')->name('reservar');
    Route::get('/r/{publico}', [MotorReservasController::class, 'reserva'])->name('reserva');
    Route::get('/r/{publico}/confirmacion.pdf', [MotorReservasController::class, 'confirmacionPdf'])->name('pdf');
    Route::post('/r/{publico}/pagar/{pasarela}', [MotorReservasController::class, 'pagar'])->middleware('throttle:10,1')->name('pagar');
    Route::get('/r/{publico}/retorno/{pasarela}', [MotorReservasController::class, 'retorno'])->name('retorno');
});
Route::post('/avisos-pago/{codigo}/{pasarela}', [MotorReservasController::class, 'aviso'])->middleware('throttle:120,1')->name('motor.aviso');

// Fases de una actualización desde el panel (instalacion-actualizaciones.md). Firmadas y solo
// para administradores (lo comprueba el controlador).
Route::middleware(['auth', 'signed'])->group(function (): void {
    Route::get('/actualizaciones/{actualizacion}/aplicar', [ActualizacionController::class, 'aplicar'])->name('actualizaciones.aplicar');
    Route::get('/actualizaciones/{actualizacion}/finalizar', [ActualizacionController::class, 'finalizar'])->name('actualizaciones.finalizar');
});
Route::middleware('auth')->get('/actualizaciones/{actualizacion}/copia-base-datos', [ActualizacionController::class, 'copiaBaseDatos'])
    ->name('actualizaciones.copia-base-datos');
