<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\Mensajero;
use App\Comunicacion\WhatsappCloud;
use App\Enums\AccionAutomatizacion;
use App\Enums\CanalEnvio;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Filament\Pages\WhatsApp;
use App\Models\Automatizacion;
use App\Models\Envio;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\CodigosAcceso;
use App\Reservas\GestorReservas;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es', 'pais' => 'MX'])->refresh();
    $unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'idioma' => 'es']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => '55 5550 0101', 'valor_normalizado' => '5555500101', 'orden' => 0]);
    $this->hoy = $this->propiedad->hoy();
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays(1)->format('Y-m-d'),
        'salida' => $this->hoy->addDays(3)->format('Y-m-d'), 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000]);
    CodigosAcceso::guardar($this->reserva, '4321');
    $this->regla = fn (array $mas = []): Automatizacion => Automatizacion::query()->create($mas + [
        'propiedad_id' => $this->propiedad->id, 'nombre' => 'Acceso', 'disparador' => DisparadorAutomatizacion::AntesLlegada, 'dias' => 1,
        'accion' => AccionAutomatizacion::WhatsappHuesped, 'whatsapp' => ['plantilla' => 'llegada_manana', 'idioma' => 'es_MX', 'variables' => ['nombre', 'codigo_acceso']],
    ]);
});

afterEach(fn () => Ajustes::olvidar());

it('manda la plantilla aprobada con sus variables en orden y guarda el token cifrado', function () {
    (new WhatsappCloud($this->propiedad->id))->guardar('1234567890', 'EAAG-token-secreto', null);
    Http::fake(['graph.facebook.com/*' => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.ABC']]])]);
    ($this->regla)();

    expect(app(Mensajero::class)->procesarTiempo())->toBe(1);
    $envio = Envio::query()->sole();
    expect($envio->canal)->toBe(CanalEnvio::WhatsappApi)->and($envio->estado)->toBe(EstadoEnvio::Pendiente)->and($envio->destino)->toContain('525555500101');

    app(Mensajero::class)->enviarPendientes();
    expect($envio->refresh()->estado)->toBe(EstadoEnvio::Enviado);
    Http::assertSent(fn (PeticionHttp $r) => $r->url() === 'https://graph.facebook.com/'.WhatsappCloud::VERSION.'/1234567890/messages'
        && $r->hasHeader('Authorization', 'Bearer EAAG-token-secreto')
        && $r['to'] === '525555500101'
        && $r['template']['name'] === 'llegada_manana' && $r['template']['language']['code'] === 'es_MX'
        && $r['template']['components'][0]['parameters'] === [['type' => 'text', 'text' => 'Ana'], ['type' => 'text', 'text' => '4321']]);
    expect(DB::table('ajustes')->where('clave', 'whatsapp.'.$this->propiedad->id.'.token')->value('valor'))->not->toContain('EAAG');
});

it('sin configurar, sin plantilla o sin teléfono lo anota omitido con la razón', function () {
    ($this->regla)();
    app(Mensajero::class)->procesarTiempo();

    expect(Envio::query()->sole())->estado->toBe(EstadoEnvio::Omitido)->ultimo_error->toBe(__('mensajes.whatsapp.sin_configurar'));
});

it('si Meta rechaza la plantilla, el envío dice por qué', function () {
    (new WhatsappCloud($this->propiedad->id))->guardar('1234567890', 'EAAG-token-secreto', null);
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => '(#132001) Template name does not exist in the translation', 'code' => 132001]], 404)]);
    ($this->regla)();
    app(Mensajero::class)->procesarTiempo();
    app(Mensajero::class)->enviarPendientes();

    expect(Envio::query()->sole()->ultimo_error)->toContain('Template name does not exist')->not->toContain('EAAG');
});

it('la condición de idioma elige la automatización de cada huésped', function () {
    (new WhatsappCloud($this->propiedad->id))->guardar('1234567890', 'EAAG-token-secreto', null);
    ($this->regla)(['condiciones' => ['idioma' => 'en']]);

    expect(app(Mensajero::class)->procesarTiempo())->toBe(0);
    $this->huesped->update(['idioma' => 'en']);
    expect(app(Mensajero::class)->procesarTiempo())->toBe(1);
});

it('se configura por propiedad desde su pantalla, sin mostrar ni auditar el token', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(WhatsApp::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'numero_id' => '1234567890', 'token' => 'EAAG-otro-token', 'version' => 'v23.0'])
        ->call('guardar')->assertHasNoFormErrors()->assertSet('datos.token', null);

    expect((new WhatsappCloud($this->propiedad->id))->configurado())->toBeTrue()
        ->and(json_encode(RegistroAuditoria::query()->pluck('cambios')->all()))->not->toContain('EAAG');
});
