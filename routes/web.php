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
use App\Http\Controllers\EnlacePagoController;
use App\Http\Controllers\EntradaInstalacionController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\LlegadaController;
use App\Http\Controllers\MotorReservasController;
use App\Http\Controllers\OpinionController;
use App\Http\Middleware\MismoOrigen;
use App\Http\Middleware\ModuloActivo;
use App\Http\Middleware\UsuarioActivo;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

// La aplicación vive en el panel de Filament (raíz «/»). Aquí solo van las rutas que no son del panel.

// Código fuente de la versión instalada, también sin iniciar sesión (AGPL; regla 8).
Route::get('/codigo-fuente', CodigoFuenteController::class)
    ->middleware('throttle:10,1')
    ->name('codigo-fuente');

// La primera entrada después de instalar, con el enlace que recibió el instalador (D-071).
Route::get('/entrar/{token}', EntradaInstalacionController::class)->where('token', '[0-9a-z]{64}')->middleware('throttle:10,1')->name('entrada-instalacion');

// Calendario de una unidad para los portales: lo identifica un token, y solo lleva fechas.
Route::get('/ical/{token}.ics', IcalController::class)->where('token', '[0-9a-f]{40}')->middleware([ModuloActivo::class.':ical', 'throttle:60,1'])->name('ical.exportar');

// Enlace de pago y encuesta: también los identifica un token.
Route::prefix('pagar/{token}')->where(['token' => '[0-9a-f]{40}'])->name('pagar.')->middleware([ModuloActivo::class.':pagos', 'throttle:publico'])->group(function (): void {
    Route::get('/', [EnlacePagoController::class, 'ver'])->name('ver');
    Route::post('/{pasarela}', [EnlacePagoController::class, 'pagar'])->middleware(['throttle:publico-envio'])->name('pagar');
    Route::get('/retorno/{pasarela}', [EnlacePagoController::class, 'retorno'])->name('retorno');
});
Route::prefix('opinion/{token}')->where(['token' => '[0-9a-f]{40}'])->name('opinion.')->middleware([ModuloActivo::class.':opiniones', 'throttle:publico'])->group(function (): void {
    Route::get('/', [OpinionController::class, 'ver'])->name('ver');
    Route::post('/', [OpinionController::class, 'guardar'])->middleware('throttle:publico-envio')->name('guardar');
});

// Llegada en línea: lo identifica un token; el huésped no tiene cuenta.
Route::prefix('llegada/{token}')->where(['token' => '[0-9a-f]{40}'])->name('llegada.')->middleware([ModuloActivo::class.':llegada', 'throttle:publico'])->group(function (): void {
    Route::get('/', [LlegadaController::class, 'formulario'])->name('formulario');
    Route::post('/', [LlegadaController::class, 'guardar'])->middleware(['throttle:publico-envio', 'throttle:llegada-envio'])->name('guardar');
    Route::get('/verificar', [LlegadaController::class, 'verificar'])->name('verificar');
    Route::post('/verificar', [LlegadaController::class, 'comprobar'])->middleware('throttle:publico-envio')->name('comprobar');
    Route::post('/reenviar', [LlegadaController::class, 'reenviar'])->middleware(['throttle:publico-codigo', 'throttle:llegada-codigo'])->name('reenviar');
    Route::get('/listo', [LlegadaController::class, 'listo'])->name('listo');
});

// Archivos privados: solo con sesión y según el permiso de su clase.
Route::get('/archivos/{archivo}', ArchivoController::class)->middleware(['auth', AuthenticateSession::class, UsuarioActivo::class, 'throttle:120,1'])->name('archivos.ver');

// Motor de reservas: páginas públicas del alojamiento (D-042). El aviso de un cobro no depende de ningún módulo: ese dinero ya se movió.
Route::prefix('reservar/{codigo}')->name('motor.')->middleware([ModuloActivo::class.':motor', 'throttle:publico'])->group(function (): void {
    Route::get('/', [MotorReservasController::class, 'buscar'])->name('buscar');
    Route::get('/datos', [MotorReservasController::class, 'datos'])->name('datos');
    Route::post('/', [MotorReservasController::class, 'reservar'])->middleware([MismoOrigen::class, 'throttle:motor-reserva'])->name('reservar');
    Route::get('/r/{publico}', [MotorReservasController::class, 'reserva'])->name('reserva');
    Route::get('/r/{publico}/confirmacion.pdf', [MotorReservasController::class, 'confirmacionPdf'])->name('pdf');
    Route::post('/r/{publico}/pagar/{pasarela}', [MotorReservasController::class, 'pagar'])->middleware([ModuloActivo::class.':pagos', MismoOrigen::class, 'throttle:publico-envio'])->name('pagar');
    Route::get('/r/{publico}/retorno/{pasarela}', [MotorReservasController::class, 'retorno'])->name('retorno');
});
Route::post('/avisos-pago/{codigo}/{pasarela}', [MotorReservasController::class, 'aviso'])->middleware('throttle:120,1')->name('motor.aviso');

// Fases de una actualización desde el panel (instalacion-actualizaciones.md). Firmadas y solo
// para el super administrador (lo comprueba el controlador).
Route::middleware(['auth', AuthenticateSession::class, UsuarioActivo::class, 'signed'])->group(function (): void {
    Route::get('/actualizaciones/{actualizacion}/instalando', [ActualizacionController::class, 'instalando'])->name('actualizaciones.instalando');
    Route::get('/actualizaciones/{actualizacion}/aplicar', [ActualizacionController::class, 'aplicar'])->name('actualizaciones.aplicar');
    Route::get('/actualizaciones/{actualizacion}/finalizar', [ActualizacionController::class, 'finalizar'])->name('actualizaciones.finalizar');
});
Route::middleware(['auth', AuthenticateSession::class, UsuarioActivo::class, 'throttle:600,1'])->post('/actualizaciones/subir-parte', [ActualizacionController::class, 'subirParte'])
    ->name('actualizaciones.subir-parte');
Route::middleware(['auth', AuthenticateSession::class, UsuarioActivo::class])->get('/actualizaciones/{actualizacion}/copia-base-datos', [ActualizacionController::class, 'copiaBaseDatos'])
    ->name('actualizaciones.copia-base-datos');
