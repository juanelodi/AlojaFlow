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
use App\Http\Controllers\GrupoPublicoController;
use App\Http\Controllers\IcalController;
use App\Http\Controllers\LlegadaController;
use App\Http\Controllers\MotorReservasController;
use App\Http\Controllers\OpinionController;
use App\Http\Controllers\PortalController;
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

// La página del coordinador de un grupo: enlace secreto y clave (D-096).
Route::prefix('grupo/{token}')->where(['token' => '[0-9a-f]{40}'])->name('grupo.')->middleware([ModuloActivo::class.':grupos', 'throttle:publico'])->group(function (): void {
    Route::get('/', [GrupoPublicoController::class, 'ver'])->name('ver');
    Route::post('/entrar', [GrupoPublicoController::class, 'entrar'])->middleware('throttle:publico-envio')->name('entrar');
    Route::post('/salir', [GrupoPublicoController::class, 'salir'])->name('salir');
    Route::post('/clave', [GrupoPublicoController::class, 'cambiarClave'])->middleware('throttle:publico-envio')->name('clave');
    Route::post('/aceptar', [GrupoPublicoController::class, 'aceptar'])->middleware('throttle:publico-envio')->name('aceptar');
    Route::post('/habitacion/{reserva}', [GrupoPublicoController::class, 'guardarHabitacion'])->whereNumber('reserva')->name('habitacion');
    Route::post('/pago', [GrupoPublicoController::class, 'registrarPago'])->middleware(['throttle:publico-envio', 'throttle:comprobante-envio'])->name('pago');
    Route::post('/mensaje', [GrupoPublicoController::class, 'escribir'])->middleware('throttle:publico-envio')->name('mensaje');
    Route::get('/comprobante/{comprobante}', [GrupoPublicoController::class, 'verComprobante'])->whereNumber('comprobante')->name('comprobante');
    Route::get('/aceptacion/{aceptacion}', [GrupoPublicoController::class, 'verCopia'])->whereNumber('aceptacion')->name('copia');
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

// La página del huésped y la guía de cada unidad (D-105, D-106). Las fotos, para el motor y para la guía.
Route::prefix('mi-reserva/{token}')->where(['token' => '[0-9a-f]{40}'])->name('mi-reserva.')->middleware([ModuloActivo::class.':portal', 'throttle:publico'])->group(function (): void {
    Route::get('/', [PortalController::class, 'miReserva'])->name('ver');
    Route::get('/confirmacion.pdf', [PortalController::class, 'confirmacion'])->name('pdf');
    Route::post('/pedir', [PortalController::class, 'pedir'])->middleware('throttle:publico-envio')->name('pedir');
    Route::post('/cancelar', [PortalController::class, 'cancelar'])->middleware('throttle:publico-envio')->name('cancelar');
    Route::post('/comprobante', [PortalController::class, 'comprobante'])->middleware(['throttle:publico-envio', 'throttle:comprobante-envio'])->name('comprobante');
});
Route::get('/guia/{token}', [PortalController::class, 'guia'])->where('token', '[0-9a-f]{40}')->middleware([ModuloActivo::class.':portal', 'throttle:publico'])->name('guia.ver');
Route::get('/fotos/{foto}/{tamano}', [PortalController::class, 'foto'])->whereNumber('foto')->whereIn('tamano', ['grande', 'chica'])->middleware('throttle:600,1')->name('fotos.ver');

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
    Route::get('/ir/{pasarela}/{referencia}', [MotorReservasController::class, 'ir'])->where('referencia', '[0-9]+-[0-9a-f]{24}')->middleware([ModuloActivo::class.':pagos', 'throttle:publico'])->name('ir');
    Route::post('/r/{publico}/comprobante', [MotorReservasController::class, 'comprobante'])->middleware([MismoOrigen::class, 'throttle:publico-envio', 'throttle:comprobante-envio'])->name('comprobante');
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
