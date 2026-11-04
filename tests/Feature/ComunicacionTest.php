<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\Mensajero;
use App\Comunicacion\MensajeTexto;
use App\Comunicacion\PlantillasBase;
use App\Comunicacion\VariablesMensaje;
use App\Enums\AccionAutomatizacion;
use App\Enums\CanalEnvio;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Filament\Pages\Correo;
use App\Filament\Resources\Automatizaciones\Pages\CreateAutomatizacion;
use App\Filament\Resources\Envios\EnvioResource;
use App\Filament\Resources\Plantillas\Pages\CreatePlantilla;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Webhooks\Pages\CreateWebhook;
use App\Models\Automatizacion;
use App\Models\Aviso;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Models\Webhook;
use App\Reservas\GestorReservas;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Mail::fake();
    // Los nombres de prueba no existen en el DNS: se hace como que apuntan a una IP pública.
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return str_contains($host, 'interno') ? ['192.168.1.10'] : ['93.184.216.34'];
        }
    });
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es', 'correo' => 'hola@posada.example'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => '+52 55 5550 0101', 'valor_normalizado' => '+525555500101', 'orden' => 1]);
    $this->hoy = $this->propiedad->hoy();
    $this->plantilla = Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'hola', 'nombre' => 'Hola', 'asunto' => ['es' => 'Reserva {numero}', 'en' => 'Booking {numero}'], 'cuerpo' => ['es' => 'Hola {nombre}: llegas el {llegada} a {unidad}.', 'en' => 'Hello {nombre}']]);
    $this->reservar = fn (array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays(3)->format('Y-m-d'), 'salida' => $this->hoy->addDays(5)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ])->load(['titular.contactos', 'unidad', 'propiedad']);
    $this->regla = fn (DisparadorAutomatizacion $disparador, array $mas = []): Automatizacion => Automatizacion::query()->create($mas + [
        'propiedad_id' => $this->propiedad->id, 'nombre' => 'Regla', 'disparador' => $disparador, 'accion' => AccionAutomatizacion::CorreoHuesped, 'plantilla_id' => $this->plantilla->id,
    ]);
    $this->configurarCorreo = function (): void {
        Ajustes::guardar('correo.host', 'smtp.example.com');
        Ajustes::guardar('correo.remitente_correo', 'hola@posada.example');
    };
    $this->mensajero = fn (): Mensajero => app(Mensajero::class);
});

afterEach(fn () => Ajustes::olvidar());

it('sustituye las variables sin ejecutar nada', function () {
    $reserva = ($this->reservar)();

    $texto = Mensajero::textoDe($this->plantilla, $reserva, 'es');

    expect($texto['asunto'])->toBe('Reserva '.$reserva->numero)
        ->and($texto['cuerpo'])->toContain('Hola Ana')->toContain('Cuarto 101')
        ->and(VariablesMensaje::aplicar('{{ 1 + 1 }} {desconocida} {nombre}', ['nombre' => 'Ana']))->toBe('{{ 1 + 1 }} {desconocida} Ana')
        ->and(Mensajero::textoDe($this->plantilla, $reserva, 'en')['cuerpo'])->toBe('Hello Ana')
        ->and(Mensajero::textoDe($this->plantilla, $reserva, 'de')['cuerpo'])->toContain('Hola Ana');
});

it('manda el correo al crear la reserva, una sola vez aunque el trabajo se repita', function () {
    ($this->configurarCorreo)();
    ($this->regla)(DisparadorAutomatizacion::ReservaCreada);
    ($this->reservar)();

    ($this->mensajero)()->procesarEventos();
    ($this->mensajero)()->procesarEventos();
    ($this->mensajero)()->enviarPendientes();
    ($this->mensajero)()->enviarPendientes();

    expect(Envio::query()->count())->toBe(1)
        ->and(Envio::query()->first()->estado)->toBe(EstadoEnvio::Enviado);
    Mail::assertSent(MensajeTexto::class, 1);
    Mail::assertSent(MensajeTexto::class, fn (MensajeTexto $m): bool => $m->hasTo('ana@example.com') && str_contains($m->cuerpo, 'Hola Ana') && $m->responderA === 'hola@posada.example');
});

it('sin correo configurado el mensaje queda listo para copiar', function () {
    ($this->regla)(DisparadorAutomatizacion::ReservaCreada);
    ($this->reservar)();

    ($this->mensajero)()->procesarEventos();
    ($this->mensajero)()->enviarPendientes();

    $envio = Envio::query()->firstOrFail();
    expect($envio->estado)->toBe(EstadoEnvio::Manual)->and($envio->cuerpo)->toContain('Hola Ana');
    Mail::assertNothingSent();
    expect(EnvioResource::getNavigationBadge())->toBeNull(); // sin sesión no ve nada
});

it('no escribe sin correo del huésped, ni por lo anterior a la regla, ni a quien ya se fue', function () {
    ($this->configurarCorreo)();
    $reservaVieja = ($this->reservar)();
    $this->travel(1)->minutes();
    ($this->regla)(DisparadorAutomatizacion::ReservaCreada);
    ($this->mensajero)()->procesarEventos();
    expect(Envio::query()->count())->toBe(0);

    $this->travel(1)->minutes();
    $sinCorreo = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luis']);
    ($this->reservar)(['titular_id' => $sinCorreo->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(11)->format('Y-m-d')]);
    ($this->mensajero)()->procesarEventos();
    expect(Envio::query()->first()->estado)->toBe(EstadoEnvio::Omitido);

    // Una estancia pasada, cargada hoy, no dispara mensajes al huésped.
    ($this->reservar)(['llegada' => $this->hoy->subDays(10)->format('Y-m-d'), 'salida' => $this->hoy->subDays(8)->format('Y-m-d')]);
    ($this->mensajero)()->procesarEventos();
    expect(Envio::query()->count())->toBe(1);
    expect($reservaVieja->exists)->toBeTrue();
});

it('distingue confirmar de cambiar y no avisa por registrar la llegada', function () {
    ($this->configurarCorreo)();
    $confirmar = ($this->regla)(DisparadorAutomatizacion::ReservaConfirmada);
    $cambiar = ($this->regla)(DisparadorAutomatizacion::ReservaModificada);
    $reserva = ($this->reservar)(['estado' => EstadoReserva::SinConfirmar->value, 'llegada' => $this->hoy->format('Y-m-d')]);

    $gestor = app(GestorReservas::class);
    $gestor->confirmar($reserva);
    $gestor->registrarEntrada($reserva->refresh());
    ($this->mensajero)()->procesarEventos();

    expect(Envio::query()->where('automatizacion_id', $confirmar->id)->count())->toBe(1)
        ->and(Envio::query()->where('automatizacion_id', $cambiar->id)->count())->toBe(0);
});

it('manda los recordatorios en su día y hora, respetando las condiciones', function () {
    ($this->configurarCorreo)();
    $this->travelTo($this->hoy->setTime(9, 0));
    ($this->regla)(DisparadorAutomatizacion::AntesLlegada, ['dias' => 3, 'hora' => '10:00', 'condiciones' => ['estado' => EstadoReserva::Confirmada->value]]);
    ($this->reservar)();
    ($this->reservar)(['estado' => EstadoReserva::SinConfirmar->value, 'llegada' => $this->hoy->addDays(3)->format('Y-m-d'), 'salida' => $this->hoy->addDays(5)->format('Y-m-d'), 'unidad_id' => Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '102', 'nombre' => '102'])->id]);

    expect(($this->mensajero)()->procesarTiempo())->toBe(0);

    $this->travelTo($this->hoy->setTime(10, 30));
    expect(($this->mensajero)()->procesarTiempo())->toBe(1)
        ->and(($this->mensajero)()->procesarTiempo())->toBe(0);
});

it('reintenta cada vez más tarde y al final avisa', function () {
    ($this->configurarCorreo)();
    ($this->regla)(DisparadorAutomatizacion::ReservaCreada);
    ($this->reservar)();
    ($this->mensajero)()->procesarEventos();
    $envio = Envio::query()->firstOrFail();
    Mail::shouldReceive('purge')->andReturnNull();
    Mail::shouldReceive('mailer')->andThrow(new RuntimeException('SMTP caído'));

    ($this->mensajero)()->enviarPendientes();
    $envio->refresh();
    expect($envio->estado)->toBe(EstadoEnvio::Pendiente)->and($envio->intentos)->toBe(1)->and($envio->ultimo_error)->toBe('SMTP caído')
        ->and($envio->programado_para->isFuture())->toBeTrue();

    // Antes de su hora no se vuelve a intentar.
    ($this->mensajero)()->enviarPendientes();
    expect($envio->refresh()->intentos)->toBe(1);

    for ($i = 0; $i < 4; $i++) {
        $this->travel(3)->hours();
        ($this->mensajero)()->enviarPendientes();
    }
    expect($envio->refresh()->estado)->toBe(EstadoEnvio::Fallido)
        ->and(Aviso::query()->where('tipo', 'envios_fallidos')->exists())->toBeTrue();
});

it('los avisos a otros sistemas van firmados y solo a redes públicas', function () {
    Http::fake(['hook.example.test/*' => Http::response('ok')]);
    Webhook::query()->create(['propiedad_id' => $this->propiedad->id, 'url' => 'https://hook.example.test/recibir?x=1', 'secreto' => 'clave', 'eventos' => [DisparadorAutomatizacion::ReservaCreada->value]]);
    Webhook::query()->create(['propiedad_id' => $this->propiedad->id, 'url' => 'https://hook.example.test/otro', 'secreto' => 'clave', 'eventos' => [DisparadorAutomatizacion::ReservaCancelada->value]]);
    Webhook::query()->create(['propiedad_id' => $this->propiedad->id, 'url' => 'https://interno.example.test/x', 'secreto' => 'clave', 'eventos' => [DisparadorAutomatizacion::ReservaCreada->value]]);
    $reserva = ($this->reservar)();

    ($this->mensajero)()->procesarEventos();
    ($this->mensajero)()->enviarPendientes();

    Http::assertSentCount(1);
    Http::assertSent(function ($peticion) use ($reserva): bool {
        $cuerpo = json_decode($peticion->body(), true);

        return str_contains($peticion->url(), 'hook.example.test/recibir')
            && $peticion->header('X-AlojaFlow-Firma')[0] === 'sha256='.hash_hmac('sha256', $peticion->body(), 'clave')
            && $cuerpo['evento'] === 'reserva.creada' && $cuerpo['reserva']['codigo'] === $reserva->codigo_publico;
    });
    $interno = Envio::query()->where('destino', 'like', '%interno%')->firstOrFail();
    expect($interno->estado)->toBe(EstadoEnvio::Pendiente)->and($interno->ultimo_error)->not->toBeNull();
});

it('el panel rechaza un aviso hacia la red interna', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(CreateWebhook::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'url' => 'https://interno.example.test/x', 'eventos' => ['reserva.creada']])
        ->call('create')->assertHasFormErrors(['url']);
    Livewire::test(CreateWebhook::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'url' => 'http://hook.example.test/x', 'eventos' => ['reserva.creada']])
        ->call('create')->assertHasFormErrors(['url']);
    Livewire::test(CreateWebhook::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'url' => 'https://hook.example.test/x', 'eventos' => ['reserva.creada']])
        ->call('create')->assertHasNoFormErrors();

    expect(Webhook::query()->count())->toBe(1);
});

it('el panel configura el correo sin volver a mostrar la contraseña y manda la prueba', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es', 'email' => 'yo@example.com']));

    Livewire::test(Correo::class)
        ->assertActionHidden('probar')
        ->fillForm(['host' => 'smtp.example.com', 'puerto' => 587, 'cifrado' => 'tls', 'usuario' => 'u', 'contrasena' => 'clave-smtp', 'remitente_correo' => 'hola@posada.example', 'remitente_nombre' => 'Posada'])
        ->call('guardar')
        ->assertHasNoFormErrors()
        ->assertDontSee('clave-smtp')
        ->callAction('probar')
        ->assertNotified(__('mensajes.correo.prueba_ok', ['correo' => 'yo@example.com']));

    Ajustes::olvidar();
    expect(Ajustes::secreto('correo.contrasena'))->toBe('clave-smtp')
        ->and((string) App\Models\Ajuste::query()->where('clave', 'correo.contrasena')->value('valor'))->not->toContain('clave-smtp');
    Mail::assertSent(MensajeTexto::class, fn (MensajeTexto $m): bool => $m->hasTo('yo@example.com'));

    // Dejar la contraseña vacía la conserva.
    Livewire::test(Correo::class)->fillForm(['host' => 'smtp2.example.com', 'puerto' => 25, 'cifrado' => 'ninguno', 'remitente_correo' => 'hola@posada.example'])->call('guardar');
    Ajustes::olvidar();
    expect(Ajustes::secreto('correo.contrasena'))->toBe('clave-smtp')->and(Ajustes::obtener('correo.host'))->toBe('smtp2.example.com');
});

it('desde la reserva manda por correo o deja el enlace de WhatsApp en el historial', function () {
    ($this->configurarCorreo)();
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
    $reserva = ($this->reservar)();

    Livewire::test(ViewReserva::class, ['record' => $reserva->getRouteKey()])
        ->callAction('mandarMensaje', ['canal' => 'correo', 'destino_correo' => 'ana@example.com', 'asunto' => 'Hola', 'cuerpo' => 'Texto'])
        ->assertHasNoActionErrors()->assertNotified(__('mensajes.mandar.enviado'));
    Mail::assertSent(MensajeTexto::class, fn (MensajeTexto $m): bool => $m->asunto === 'Hola');

    Livewire::test(ViewReserva::class, ['record' => $reserva->getRouteKey()])
        ->callAction('mandarMensaje', ['canal' => 'whatsapp_enlace', 'destino_telefono' => '+52 55 5550 0101', 'cuerpo' => 'Texto'])
        ->assertHasNoActionErrors();
    expect(Envio::query()->where('canal', CanalEnvio::WhatsappEnlace->value)->first()->destino)->toBe('+52 55 5550 0101');
    expect(VariablesMensaje::enlaceWhatsapp('+52 55 5550 0101', 'Hola Ana'))->toBe('https://wa.me/525555500101?text=Hola%20Ana');
});

it('crea plantillas y automatizaciones desde el panel, y las de partida', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(CreatePlantilla::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Nueva', 'codigo' => 'nueva', 'asunto' => ['es' => 'A'], 'cuerpo' => ['es' => 'B']])
        ->call('create')->assertHasNoFormErrors();
    expect(Plantilla::query()->where('codigo', 'nueva')->first()->texto('cuerpo', 'es'))->toBe('B');

    Livewire::test(CreateAutomatizacion::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Regla', 'disparador' => 'antes_llegada', 'dias' => 2, 'hora' => '10:00', 'accion' => 'correo_huesped', 'plantilla_id' => $this->plantilla->id])
        ->call('create')->assertHasNoFormErrors();
    expect(Automatizacion::query()->first()->dias)->toBe(2);

    expect(PlantillasBase::sembrar($this->propiedad))->toBe(5)->and(PlantillasBase::sembrar($this->propiedad))->toBe(0);
    expect(Plantilla::query()->where('codigo', 'confirmacion')->first()->texto('cuerpo', 'en'))->toContain('is confirmed');
});

it('avisa al alojamiento de una reserva del motor y borra el texto viejo', function () {
    ($this->configurarCorreo)();
    $reserva = ($this->reservar)();

    ($this->mensajero)()->avisarNuevaReserva($reserva, 'hola@posada.example');
    ($this->mensajero)()->avisarNuevaReserva($reserva, 'hola@posada.example');
    expect(Envio::query()->count())->toBe(1);

    $this->travel(91)->days();
    $this->artisan('alojaflow:limpiar')->assertSuccessful();
    ($this->mensajero)()->enviarPendientes();
    Envio::query()->update(['estado' => EstadoEnvio::Enviado->value]);
    $this->artisan('alojaflow:limpiar')->assertSuccessful();
    expect(Envio::query()->first()->cuerpo)->toBeNull();
});
