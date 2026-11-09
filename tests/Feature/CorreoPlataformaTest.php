<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\AjustesCorreo;
use App\Comunicacion\MensajeTexto;
use App\Filament\Pages\Correo;
use App\Filament\Pages\EstadoSistema;
use App\Models\Propiedad;
use App\Models\User;
use App\Modulos\PrimerosPasos;
use App\Seguridad\Acceso;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

beforeEach(function () {
    // El proveedor de la plataforma, como lo escribe AlojaFlow App en el .env de la instancia.
    config([
        'mail.default' => 'smtp',
        'mail.mailers.smtp.host' => 'email-smtp.us-east-1.amazonaws.com',
        'mail.from' => ['address' => 'avisos@avisos.alojaflow.com', 'name' => 'AlojaFlow'],
    ]);
    // Sin red de verdad: lo que diga cada prueba.
    $this->ips = ['smtp.posada.example' => ['93.184.216.34'], 'interno.posada.example' => ['10.0.0.5'], 'mixto.posada.example' => ['93.184.216.34', '127.0.0.1']];
    $ips = &$this->ips;
    $this->app->instance(ValidadorDestino::class, new class ($ips) extends ValidadorDestino {
        /** @param array<string, list<string>> $ips */
        public function __construct(private array &$ips)
        {
        }

        protected function resolver(string $host): array
        {
            return filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : ($this->ips[$host] ?? []);
        }
    });
});

afterEach(fn () => Ajustes::olvidar());

it('en una instancia, el correo del .env cuenta como configurado y sale con el remitente de la plataforma', function () {
    comoInstancia();
    $correo = app(AjustesCorreo::class);

    expect($correo->configurado())->toBeTrue()
        ->and($correo->dePlataforma())->toBeTrue()
        ->and($correo->propio())->toBeFalse()
        ->and($correo->prepararMailer())->toBe('smtp')
        ->and($correo->remitente())->toBe('avisos@avisos.alojaflow.com');

    // El remitente que escribió el alojamiento solo vale con su propio servidor: el proveedor no lo dejaría pasar.
    Ajustes::guardar('correo.remitente_correo', 'hola@posada.example');
    expect($correo->remitente())->toBe('avisos@avisos.alojaflow.com');
});

it('fuera de una instancia, el correo del .env no cuenta: se configura en el panel', function () {
    $correo = app(AjustesCorreo::class);

    expect($correo->configurado())->toBeFalse()->and($correo->dePlataforma())->toBeFalse();
});

it('una instancia sin proveedor (correo al registro) tampoco cuenta', function () {
    comoInstancia();
    config(['mail.default' => 'log']);

    expect(app(AjustesCorreo::class)->configurado())->toBeFalse();
});

it('con el correo de la plataforma se manda y se puede pedir otra contraseña', function () {
    comoInstancia();
    Mail::fake();
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es', 'email' => 'yo@example.com']));

    Livewire::test(Correo::class)
        ->assertSee(__('mensajes.correo.plataforma', ['remitente' => 'avisos@avisos.alojaflow.com']))
        ->assertActionVisible('probar')
        ->callAction('probar')
        ->assertNotified(__('mensajes.correo.prueba_ok', ['correo' => 'yo@example.com']));
    Mail::assertSent(MensajeTexto::class, fn (MensajeTexto $m): bool => $m->hasTo('yo@example.com') && $m->correoRemitente === 'avisos@avisos.alojaflow.com');

    auth()->logout();
    $this->get(Filament::getRequestPasswordResetUrl())->assertOk();
});

it('en una instancia, el servidor SMTP del alojamiento debe estar en una red pública', function () {
    comoInstancia();
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
    $datos = ['puerto' => 587, 'cifrado' => 'tls', 'usuario' => 'u', 'remitente_correo' => 'hola@posada.example'];

    foreach (['interno.posada.example', 'mixto.posada.example', '127.0.0.1', 'no-existe.posada.example'] as $host) {
        Livewire::test(Correo::class)->fillForm(['host' => $host] + $datos)->call('guardar')->assertHasFormErrors(['host']);
    }
    expect(Ajustes::obtener('correo.host'))->toBeNull();

    Livewire::test(Correo::class)->fillForm(['host' => 'smtp.posada.example'] + $datos)->call('guardar')->assertHasNoFormErrors();
    expect(Ajustes::obtener('correo.host'))->toBe('smtp.posada.example');
});

it('en una instancia, se conecta a la IP ya revisada y comprueba el certificado con el nombre', function () {
    comoInstancia();
    Ajustes::guardar('correo.host', 'smtp.posada.example');
    Ajustes::guardar('correo.remitente_correo', 'hola@posada.example');
    $correo = app(AjustesCorreo::class);

    expect($correo->prepararMailer())->toBe(AjustesCorreo::MAILER)
        ->and($correo->remitente())->toBe('hola@posada.example')
        ->and(config('mail.mailers.'.AjustesCorreo::MAILER.'.host'))->toBe('93.184.216.34');
    $transporte = Mail::mailer(AjustesCorreo::MAILER)->getSymfonyTransport();
    expect($transporte)->toBeInstanceOf(EsmtpTransport::class);
    /** @var EsmtpTransport $transporte */
    expect($transporte->getStream()->getStreamOptions()['ssl']['peer_name'] ?? null)->toBe('smtp.posada.example');

    // Si después el nombre apunta a la red interna, ya no se conecta.
    $this->ips['smtp.posada.example'] = ['192.168.1.20'];
    expect(fn () => $correo->prepararMailer())->toThrow(RuntimeException::class);
});

it('fuera de una instancia, el SMTP puede estar en la red local, como siempre', function () {
    Ajustes::guardar('correo.host', 'interno.posada.example');
    app(AjustesCorreo::class)->prepararMailer();

    expect(config('mail.mailers.'.AjustesCorreo::MAILER.'.host'))->toBe('interno.posada.example');
});

it('el estado del sistema y los primeros pasos dan el correo por hecho', function () {
    comoInstancia();
    $administrador = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($administrador);

    $filas = collect((new EstadoSistema())->filas())->keyBy('titulo');
    expect($filas[__('ui.estado.correo')]['estado'])->toBe('ok')
        ->and($filas[__('ui.estado.correo')]['valor'])->toBe(__('ui.estado.correo_plataforma'))
        ->and(collect(PrimerosPasos::de($administrador))->firstWhere('clave', 'correo')['hecho'])->toBeTrue();
});

it('quien no es administrador no configura el correo', function () {
    comoInstancia();
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($gerente, [['propiedad_id' => $propiedad->id, 'rol' => 'gerente']]);

    $this->actingAs($gerente)->get(Correo::getUrl())->assertForbidden();
});
