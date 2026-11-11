<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Comunicacion\MensajeTexto;
use App\Filament\Pages\Resumen;
use App\Filament\Pages\SoporteAlojaflow;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Seguridad\AccesoSoporte;
use App\Support\Ajustes;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->dueno = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es', 'email' => 'duena@example.com']);
});

afterEach(fn () => Ajustes::olvidar());

it('sin el permiso del cliente, el soporte no entra', function () {
    comoInstancia();

    [$codigo, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 30]);

    expect($codigo)->not->toBe(0)
        ->and($respuesta['enlace'])->toBeNull()
        ->and($respuesta['error'])->toBe(__('soporte.sin_permiso'))
        ->and(User::query()->where('es_soporte', true)->exists())->toBeFalse();
});

it('fuera del servicio alojado no hay acceso de soporte ni pantalla', function () {
    app(AccesoSoporte::class)->permitir($this->dueno);

    [$codigo, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 30]);
    expect($codigo)->not->toBe(0)->and($respuesta['error'])->toBe(__('soporte.solo_instancia'));

    $this->actingAs($this->dueno)->get(SoporteAlojaflow::getUrl())->assertForbidden();
});

it('el cliente da permiso 24 horas y el soporte entra una vez con el enlace, con su nombre en la auditoría', function () {
    comoInstancia();
    $this->actingAs($this->dueno);
    Livewire::test(SoporteAlojaflow::class)->callAction('permitir')->assertNotified();
    expect(app(AccesoSoporte::class)->permitidoHasta()?->diffInHours(now(), true))->toBeGreaterThan(23.9);
    auth()->logout();

    [$codigo, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan Pérez', '--minutos' => 60]);
    expect($codigo)->toBe(0)
        ->and($respuesta['usuario'])->toBe(__('soporte.usuario', ['nombre' => 'Juan Pérez'], 'es'))
        ->and($respuesta['enlace_caduca_en_min'])->toBe(5);
    $soporte = User::query()->where('es_soporte', true)->firstOrFail();
    expect($soporte->name)->toBe('Soporte AlojaFlow — Juan Pérez')
        ->and($soporte->es_administrador)->toBeTrue()
        ->and($soporte->es_superadministrador)->toBeFalse()
        ->and($soporte->debe_crear_contrasena)->toBeFalse()
        ->and(RegistroAuditoria::query()->where('accion', 'soporte.acceso')->where('modelo_id', $soporte->id)->exists())->toBeTrue();

    $ruta = (string) parse_url((string) $respuesta['enlace'], PHP_URL_PATH);
    $this->get($ruta)->assertRedirect(Resumen::getUrl());
    $this->assertAuthenticatedAs($soporte);
    // El soporte no se da permiso a sí mismo.
    $this->get(SoporteAlojaflow::getUrl())->assertForbidden();

    // El enlace sirve una vez.
    auth()->logout();
    $this->get($ruta)->assertRedirect(Resumen::getUrl());
    $this->assertGuest();
});

it('el enlace del soporte caduca a los 5 minutos y no gasta el del dueño', function () {
    comoInstancia();
    // El dueño todavía no crea su contraseña: solo entonces le sirve un enlace.
    $this->dueno->forceFill(['debe_crear_contrasena' => true])->save();
    app(AccesoSoporte::class)->permitir($this->dueno);
    [, $delDueno] = comandoJson('alojaflow:enlace-entrada', []);
    [, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 60]);

    $this->travel(6)->minutes();
    $this->get((string) parse_url((string) $respuesta['enlace'], PHP_URL_PATH));
    $this->assertGuest();

    $this->get((string) parse_url((string) $delDueno['enlace'], PHP_URL_PATH));
    $this->assertAuthenticatedAs($this->dueno);
});

it('al vencer el tiempo el soporte queda fuera y se desactiva solo', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);
    [, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 30]);
    $this->get((string) parse_url((string) $respuesta['enlace'], PHP_URL_PATH));
    $soporte = User::query()->where('es_soporte', true)->firstOrFail();
    $this->get(Resumen::getUrl())->assertOk();

    $this->travel(31)->minutes();
    // Aunque el programador no haya corrido, ya no entra.
    $this->get(Resumen::getUrl())->assertForbidden();

    // La tarea de cada minuto lo desactiva.
    $tarea = collect(app(Schedule::class)->events())->first(fn ($evento): bool => $evento->description === 'soporte-vencer');
    expect($tarea)->not->toBeNull();
    $tarea->run($this->app);
    expect($soporte->refresh()->activo)->toBeFalse()
        ->and(RegistroAuditoria::query()->where('accion', 'soporte.vencido')->where('modelo_id', $soporte->id)->exists())->toBeTrue();
});

it('con permiso, el acceso no dura más que el permiso', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);
    $this->travel(23)->hours();

    [, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 600]);

    expect(Illuminate\Support\Carbon::parse((string) $respuesta['hasta'])->lte(app(AccesoSoporte::class)->permitidoHasta()))->toBeTrue();
});

it('retirar el permiso saca al soporte que está dentro', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);
    comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 60]);

    $this->actingAs($this->dueno);
    Livewire::test(SoporteAlojaflow::class)->assertSee('Juan')->callAction('retirar')->assertNotified();

    expect(app(AccesoSoporte::class)->permitidoHasta())->toBeNull()
        ->and(User::query()->where('es_soporte', true)->value('activo'))->toBeFalse();
});

it('reactivar al mismo soporte no crea otro usuario', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);
    comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 10]);
    app(AccesoSoporte::class)->vencer(todos: true);

    [$codigo] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 10]);

    expect($codigo)->toBe(0)
        ->and(User::query()->where('es_soporte', true)->count())->toBe(1)
        ->and(User::query()->where('es_soporte', true)->value('activo'))->toBeTrue();
});

it('en una emergencia entra sin permiso, con el motivo, y el cliente recibe un correo', function () {
    comoInstancia();
    config(['mail.default' => 'smtp', 'mail.from' => ['address' => 'avisos@avisos.alojaflow.com', 'name' => 'AlojaFlow']]);
    Mail::fake();

    [$codigo, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 30, '--emergencia' => 'El motor de reservas no responde']);

    expect($codigo)->toBe(0)->and($respuesta['aviso_enviado'])->toBeTrue();
    Mail::assertSent(MensajeTexto::class, fn (MensajeTexto $m): bool => $m->hasTo('duena@example.com') && str_contains($m->cuerpo, 'El motor de reservas no responde'));
    expect(RegistroAuditoria::query()->where('accion', 'soporte.acceso')->value('cambios'))->toMatchArray(['emergencia' => 'El motor de reservas no responde']);
});

it('rechaza un nombre vacío o un tiempo fuera de rango', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);

    expect(comandoJson('alojaflow:acceso-soporte', ['--nombre' => ' ', '--minutos' => 30])[0])->not->toBe(0)
        ->and(comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 0])[0])->not->toBe(0)
        ->and(comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 5000])[0])->not->toBe(0)
        ->and(User::query()->where('es_soporte', true)->exists())->toBeFalse();
});

it('quien no es administrador no da permiso al soporte', function () {
    comoInstancia();
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($gerente, [['propiedad_id' => $propiedad->id, 'rol' => 'gerente']]);

    $this->actingAs($gerente)->get(SoporteAlojaflow::getUrl())->assertForbidden();
});

it('el soporte ve al equipo pero no crea ni cambia usuarios', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);
    [, $respuesta] = comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Juan', '--minutos' => 30]);
    $this->get((string) parse_url((string) $respuesta['enlace'], PHP_URL_PATH));
    $recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);

    $this->get(UsuarioResource::getUrl('index'))->assertOk();
    $this->get(UsuarioResource::getUrl('create'))->assertForbidden();
    $this->get(UsuarioResource::getUrl('edit', ['record' => $recepcion]))->assertForbidden();
});

it('dos nombres que dan el mismo slug no comparten cuenta ni auditoría', function () {
    comoInstancia();
    app(AccesoSoporte::class)->permitir($this->dueno);

    comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'José Pérez', '--minutos' => 10]);
    $jose = User::query()->where('es_soporte', true)->sole();
    comandoJson('alojaflow:acceso-soporte', ['--nombre' => 'Jose Perez', '--minutos' => 10]);

    expect(User::query()->where('es_soporte', true)->count())->toBe(2)
        ->and($jose->refresh()->name)->toContain('José Pérez');
});
