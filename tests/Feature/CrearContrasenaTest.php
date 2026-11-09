<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Filament\Pages\CrearContrasena;
use App\Filament\Pages\Resumen;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

it('quien se instaló sin navegador crea su contraseña al entrar con el enlace, sin escribir la actual', function () {
    [, $respuesta] = comandoJson('alojaflow:instalar', ['--nombre' => 'Dueña', '--correo' => 'duena@example.com', '--idioma' => 'es']);
    $dueno = User::query()->where('email', 'duena@example.com')->firstOrFail();
    expect($dueno->debe_crear_contrasena)->toBeTrue();

    $ruta = (string) parse_url((string) $respuesta['enlace'], PHP_URL_PATH);
    $this->get($ruta)->assertRedirect(CrearContrasena::getUrl());
    $this->assertAuthenticatedAs($dueno);

    // Mientras no la crea, cualquier otra pantalla lo manda ahí.
    $this->get(Resumen::getUrl())->assertRedirect(CrearContrasena::getUrl());
    $this->get('/reservas')->assertRedirect(CrearContrasena::getUrl());
    $this->get(CrearContrasena::getUrl())->assertOk()->assertSee(__('permisos.crear_contrasena.titulo'));

    Livewire::test(CrearContrasena::class)
        ->set('datos.contrasena', 'mi-contrasena-nueva-123')
        ->set('datos.confirmacion', 'mi-contrasena-nueva-123')
        ->call('guardar')
        ->assertHasNoErrors()
        ->assertRedirect(Resumen::getUrl());

    $dueno->refresh();
    expect($dueno->debe_crear_contrasena)->toBeFalse()
        ->and(Hash::check('mi-contrasena-nueva-123', $dueno->password))->toBeTrue()
        ->and(RegistroAuditoria::query()->where('accion', 'usuario.crear_contrasena')->where('user_id', $dueno->id)->exists())->toBeTrue();

    // Ya no se le pide, y la sesión sigue abierta con la contraseña nueva: sigue a las primeras preguntas.
    $this->get(Resumen::getUrl())->assertRedirect(App\Filament\Pages\PrimerasPreguntas::getUrl());
    $this->get(CrearContrasena::getUrl())->assertForbidden();
});

it('pide una contraseña larga y escrita dos veces igual', function () {
    $dueno = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $dueno->forceFill(['debe_crear_contrasena' => true])->save();
    $anterior = $dueno->password;
    $this->actingAs($dueno);

    Livewire::test(CrearContrasena::class)
        ->set('datos.contrasena', 'corta')->set('datos.confirmacion', 'corta')
        ->call('guardar')->assertHasErrors(['datos.contrasena']);
    Livewire::test(CrearContrasena::class)
        ->set('datos.contrasena', 'mi-contrasena-nueva-123')->set('datos.confirmacion', 'otra-contrasena-nueva-456')
        ->call('guardar')->assertHasErrors(['datos.contrasena']);

    expect($dueno->refresh()->password)->toBe($anterior)->and($dueno->debe_crear_contrasena)->toBeTrue();
});

it('a quien ya tiene contraseña, también sin ser administrador, no se le pide', function () {
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($recepcion, [['propiedad_id' => $propiedad->id, 'rol' => 'recepcion']]);

    $this->actingAs($recepcion)->get('/')->assertOk();
    $this->get(CrearContrasena::getUrl())->assertForbidden();
});

it('quien no es administrador y tiene la contraseña pendiente también va a crearla', function () {
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($recepcion, [['propiedad_id' => $propiedad->id, 'rol' => 'recepcion']]);
    $recepcion->forceFill(['debe_crear_contrasena' => true])->save();

    $this->actingAs($recepcion)->get('/')->assertRedirect(CrearContrasena::getUrl());
    $this->get(CrearContrasena::getUrl())->assertOk();
});

it('cambiar la contraseña por otro camino (recuperarla por correo) quita la marca', function () {
    $dueno = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $dueno->forceFill(['debe_crear_contrasena' => true])->save();

    $dueno->forceFill(['password' => 'la-recuperada-por-correo'])->save();

    expect($dueno->refresh()->debe_crear_contrasena)->toBeFalse();
});

it('el instalador web no marca a nadie: ahí ya se escribió la contraseña', function () {
    $dueno = app(App\Instalacion\Instalador::class)->crearAdministrador('Dueña', 'duena@example.com', 'la-del-instalador-web');

    expect($dueno->refresh()->debe_crear_contrasena)->toBeFalse();
});
