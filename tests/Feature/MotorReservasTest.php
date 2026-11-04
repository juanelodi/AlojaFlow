<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\BaseCargo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoEventoReserva;
use App\Filament\Pages\MotorDeReservas;
use App\Models\Aviso;
use App\Models\ConceptoCargo;
use App\Models\Huesped;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Reservas\ReservaRechazada;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'descripcion_publica' => 'Cama matrimonial']);
    $this->unidad1 = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'orden' => 1]);
    $this->unidad2 = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2, 'orden' => 2]);
    $this->hoy = $this->propiedad->hoy();
    $this->formulario = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
    $this->datos = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => null, 'comentario' => null, 'cupon' => null, 'cargos' => [], 'idioma' => 'es',
    ];
});

afterEach(fn () => Ajustes::olvidar());

it('muestra las tarifas con lugar y su precio', function () {
    $url = '/reservar/posada?llegada='.$this->hoy->addDays(5)->format('Y-m-d').'&salida='.$this->hoy->addDays(7)->format('Y-m-d').'&personas=2';

    $this->get($url)->assertOk()->assertSee('Doble')->assertSee('Cama matrimonial')->assertSee('$2,000.00');

    // Sin lugar para 3 personas.
    $this->get(str_replace('personas=2', 'personas=3', $url))->assertOk()->assertSee(__('motor.sin_resultados'));
});

it('no abre si el motor está apagado, si la propiedad es subordinada o no existe', function () {
    (new AjustesMotor($this->propiedad))->guardar(['activo' => false]);
    $this->get('/reservar/posada')->assertNotFound();
    $this->get('/reservar/no-existe')->assertNotFound();

    Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'ajustes' => ['motor' => ['activo' => true]]]);
    $this->get('/reservar/sub')->assertNotFound();
});

it('reserva sin pasarelas: queda por revisar y sin retención', function () {
    $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();

    $reserva = Reserva::query()->firstOrFail();
    expect($reserva->estado)->toBe(EstadoReserva::SinConfirmar)
        ->and($reserva->expira_en)->toBeNull()
        ->and($reserva->total)->toBe(200000)
        ->and($reserva->deposito)->toBe(100000)
        ->and($reserva->unidad_id)->toBe($this->unidad1->id)
        ->and($reserva->origen_texto)->toBe(__('motor.origen'))
        ->and($reserva->titular->contactos()->where('valor_normalizado', 'ana@example.com')->exists())->toBeTrue();

    $this->get('/reservar/posada/r/'.$reserva->codigo_publico)->assertOk()->assertSee($reserva->codigo_publico)->assertSee(__('motor.estado_pendiente'));
    $this->get('/reservar/posada/r/NOEXISTE00')->assertNotFound();
    $this->get('/reservar/posada/r/'.$reserva->codigo_publico.'/confirmacion.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
});

it('con confirmación automática la reserva queda confirmada', function () {
    (new AjustesMotor($this->propiedad))->guardar(['confirmacion_inmediata' => true, 'anticipo_porcentaje' => 0]);
    $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();

    expect(Reserva::query()->firstOrFail()->estado)->toBe(EstadoReserva::Confirmada);
});

it('muestra el formulario con el desglose y vuelve a buscar si ya no hay lugar', function () {
    $consulta = ['tarifa' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2];
    ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorEstancia, 'importe' => 10000]);

    $this->get('/reservar/posada/datos?'.http_build_query($consulta))->assertOk()->assertSee('Doble')->assertSee('$2,000.00')->assertSee('Desayuno');

    $motor = app(MotorReservas::class);
    $motor->reservar($this->propiedad, ($this->datos)());
    $motor->reservar($this->propiedad, ($this->datos)(['correo' => 'b@example.com']));

    $this->get('/reservar/posada/datos?'.http_build_query($consulta))->assertRedirectContains('aviso=sin_lugar');
    $this->get('/reservar/posada?aviso=sin_lugar')->assertSee(__('motor.error.sin_lugar'));
    $this->get('/reservar/posada?aviso=<script>')->assertDontSee('<script>', false)->assertOk();
});

it('valida el formulario, el campo trampa y las condiciones', function () {
    // Los errores salen en la misma respuesta y conservan lo escrito: dentro de un iframe no hay sesión.
    $this->post('/reservar/posada', ($this->formulario)(['correo' => 'no-es-correo']))->assertStatus(422)->assertSee('no-es-correo')->assertSee('Ana');
    $this->post('/reservar/posada', ($this->formulario)(['sitio_web' => 'http://spam.example']))->assertStatus(422);
    $datos = ($this->formulario)();
    unset($datos['acepto']);
    $this->post('/reservar/posada', $datos)->assertStatus(422);
    expect(Reserva::query()->count())->toBe(0);
});

it('asigna otra unidad si la primera se ocupa y avisa cuando no queda lugar', function () {
    $motor = app(MotorReservas::class);

    expect($motor->reservar($this->propiedad, ($this->datos)())->unidad_id)->toBe($this->unidad1->id)
        ->and($motor->reservar($this->propiedad, ($this->datos)(['correo' => 'luis@example.com']))->unidad_id)->toBe($this->unidad2->id);

    expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['correo' => 'otra@example.com'])))
        ->toThrow(ReservaRechazada::class, __('motor.error.sin_lugar'));
});

it('reutiliza la ficha del mismo correo sin cambiarla', function () {
    $motor = app(MotorReservas::class);
    $motor->reservar($this->propiedad, ($this->datos)());
    $motor->reservar($this->propiedad, ($this->datos)(['nombre' => 'Otro nombre', 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(11)->format('Y-m-d')]));

    expect(Huesped::query()->count())->toBe(1)->and(Huesped::query()->first()->nombre)->toBe('Ana');
});

it('respeta la estancia mínima, la antelación y solo cobra cargos del catálogo público', function () {
    $motor = app(MotorReservas::class);
    $this->tarifa->update(['estancia_minima' => 3]);
    expect(fn () => $motor->reservar($this->propiedad, ($this->datos)()))->toThrow(ReservaRechazada::class, __('motor.error.estancia_minima', ['noches' => 3]));
    $this->tarifa->update(['estancia_minima' => 1]);

    expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['llegada' => $this->hoy->subDay()->format('Y-m-d')])))->toThrow(ReservaRechazada::class);

    $publico = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorEstancia, 'importe' => 10000]);
    $oculto = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Interno', 'base' => BaseCargo::PorEstancia, 'importe' => 99900, 'visible_motor' => false]);
    $reserva = $motor->reservar($this->propiedad, ($this->datos)(['cargos' => [$publico->id, $oculto->id]]));

    expect($reserva->cargos()->pluck('nombre')->all())->toBe(['Desayuno'])->and($reserva->total)->toBe(210000);
});

describe('Stripe', function () {
    beforeEach(function () {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
        $ajustes->guardarLlave('stripe_webhook', 'whsec_x');
        $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
    });

    it('retiene la reserva y abre el cobro por el anticipo', function () {
        expect($this->reserva->expira_en)->not->toBeNull();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/1'])]);

        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/stripe')->assertRedirect('https://checkout.stripe.test/1');

        Http::assertSent(fn ($r) => str_contains($r->url(), 'checkout/sessions')
            && $r['line_items'][0]['price_data']['unit_amount'] === 100000
            && $r['line_items'][0]['price_data']['currency'] === 'mxn'
            && $r->hasHeader('Authorization', 'Bearer sk_test_x'));
        expect(PagoEnLinea::query()->first()->referencia)->toBe('cs_test_1');
    });

    it('al volver consulta a Stripe y anota el pago una sola vez', function () {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 100000, 'currency' => 'mxn', 'payment_intent' => 'pi_1']),
        ]);
        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/stripe');
        $cobro = PagoEnLinea::query()->firstOrFail();

        $url = '/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/stripe?cobro='.$cobro->id.'&session_id=cs_test_1';
        $this->get($url)->assertRedirect();
        $this->get($url)->assertRedirect();

        $this->reserva->refresh();
        expect($this->reserva->pagado)->toBe(100000)
            ->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($this->reserva->expira_en)->toBeNull()
            ->and($this->reserva->pagos()->count())->toBe(1)
            ->and($this->reserva->eventos()->where('tipo', TipoEventoReserva::Modificada->value)->exists())->toBeTrue();
        $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico)->assertSee(__('motor.estado_confirmada'));
    });

    it('no cree lo que diga la dirección si Stripe dice que no se pagó', function () {
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/1']),
            'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'unpaid', 'amount_total' => 100000, 'currency' => 'mxn']),
        ]);
        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/stripe');
        $cobro = PagoEnLinea::query()->firstOrFail();
        $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/stripe?cobro='.$cobro->id.'&payment_status=paid');

        expect($this->reserva->refresh()->pagado)->toBe(0)->and($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar);
    });

    it('el aviso firmado confirma; sin firma válida o con otro importe, no', function () {
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/1'])]);
        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/stripe');

        $evento = fn (int $importe): string => (string) json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => ['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => $importe, 'currency' => 'mxn']]]);
        $firmar = fn (string $cuerpo, string $secreto = 'whsec_x'): string => 't='.time().',v1='.hash_hmac('sha256', time().'.'.$cuerpo, $secreto);
        $enviar = fn (string $cuerpo, string $firma) => $this->call('POST', '/avisos-pago/posada/stripe', [], [], [], ['HTTP_Stripe-Signature' => $firma, 'CONTENT_TYPE' => 'application/json'], $cuerpo);

        $enviar($evento(100000), $firmar($evento(100000), 'otro'))->assertStatus(400);
        expect($this->reserva->refresh()->pagado)->toBe(0);

        $enviar($evento(5), $firmar($evento(5)))->assertOk();
        expect($this->reserva->refresh()->pagado)->toBe(0)->and(Aviso::query()->where('tipo', 'pago_no_coincide')->exists())->toBeTrue();

        $enviar($evento(100000), $firmar($evento(100000)))->assertOk();
        $enviar($evento(100000), $firmar($evento(100000)))->assertOk();
        expect($this->reserva->refresh()->pagado)->toBe(100000)->and($this->reserva->pagos()->count())->toBe(1);
    });
});

describe('Mercado Pago', function () {
    beforeEach(function () {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardarLlave('mercadopago_token', 'TEST-token');
        $ajustes->guardarLlave('mercadopago_webhook', 'secreto');
        $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref_1', 'init_point' => 'https://mp.test/pagar']),
            'api.mercadopago.com/v1/payments/555' => fn () => Http::response(['id' => 555, 'status' => 'approved', 'transaction_amount' => 1000.0, 'currency_id' => 'MXN', 'external_reference' => (string) PagoEnLinea::query()->firstOrFail()->id]),
        ]);
    });

    it('abre la preferencia y confirma con el pago consultado a su API', function () {
        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/mercadopago')->assertRedirect('https://mp.test/pagar');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'checkout/preferences') && $r['items'][0]['unit_price'] === 1000.0 && $r['items'][0]['currency_id'] === 'MXN');
        $cobro = PagoEnLinea::query()->firstOrFail();

        $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/mercadopago?cobro='.$cobro->id.'&payment_id=555&status=approved')->assertRedirect();

        expect($this->reserva->refresh()->pagado)->toBe(100000)->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada);
    });

    it('el aviso con firma válida confirma; con firma mala, no', function () {
        $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/mercadopago');
        $ts = (string) time();
        $firma = hash_hmac('sha256', 'id:555;request-id:req-1;ts:'.$ts.';', 'secreto');
        $cuerpo = ['type' => 'payment', 'data' => ['id' => '555']];

        $this->postJson('/avisos-pago/posada/mercadopago', $cuerpo, ['x-signature' => 'ts='.$ts.',v1=mala', 'x-request-id' => 'req-1'])->assertStatus(400);
        expect($this->reserva->refresh()->pagado)->toBe(0);

        $this->postJson('/avisos-pago/posada/mercadopago', $cuerpo, ['x-signature' => 'ts='.$ts.',v1='.$firma, 'x-request-id' => 'req-1'])->assertOk();
        expect($this->reserva->refresh()->pagado)->toBe(100000);
    });
});

it('cancela las reservas retenidas que no se pagaron y libera sus noches', function () {
    (new AjustesMotor($this->propiedad))->guardarLlave('stripe_secreta', 'sk_test_x');
    $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
    $reserva = Reserva::query()->firstOrFail();

    $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
    expect($reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);

    $this->travel(31)->minutes();
    $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
    expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada)->and($reserva->nochesOcupadas()->count())->toBe(0);
});

it('un pago que llega después de vencer recupera la reserva; si ya no hay lugar, avisa', function () {
    (new AjustesMotor($this->propiedad))->guardarLlave('stripe_secreta', 'sk_test_x');
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://s.test/1']),
        'api.stripe.com/v1/checkout/sessions/cs_1' => Http::response(['id' => 'cs_1', 'payment_status' => 'paid', 'amount_total' => 100000, 'currency' => 'mxn']),
    ]);
    $this->post('/reservar/posada', ($this->formulario)());
    $reserva = Reserva::query()->firstOrFail();
    $this->post('/reservar/posada/r/'.$reserva->codigo_publico.'/pagar/stripe');
    $cobro = PagoEnLinea::query()->firstOrFail();
    $this->travel(31)->minutes();
    $this->artisan('alojaflow:reservas-vencidas');
    // Las dos unidades se venden mientras tanto.
    $motor = app(MotorReservas::class);
    $motor->reservar($this->propiedad, ($this->datos)(['correo' => 'b@example.com']));
    $motor->reservar($this->propiedad, ($this->datos)(['correo' => 'c@example.com']));

    $this->get('/reservar/posada/r/'.$reserva->codigo_publico.'/retorno/stripe?cobro='.$cobro->id);

    expect($reserva->refresh()->pagado)->toBe(100000)
        ->and($reserva->estado)->toBe(EstadoReserva::Cancelada)
        ->and(Aviso::query()->where('tipo', 'pago_sin_lugar')->exists())->toBeTrue();
});

it('el panel guarda los ajustes y las llaves cifradas, sin mostrarlas ni anotarlas', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(MotorDeReservas::class)
        ->set('datos.activo', true)
        ->set('datos.anticipo_porcentaje', 40)
        ->set('datos.minutos_retencion', 20)
        ->set('datos.dias_antelacion', 1)
        ->set('datos.stripe_secreta', 'sk_test_secreta')
        ->call('guardar')
        ->assertHasNoFormErrors()
        ->assertDontSee('sk_test_secreta');

    $ajustes = new AjustesMotor($this->propiedad->refresh());
    Ajustes::olvidar();
    expect($ajustes->anticipoPorcentaje())->toBe(40)
        ->and($ajustes->llave('stripe_secreta'))->toBe('sk_test_secreta')
        ->and(json_encode(App\Models\Ajuste::query()->where('clave', 'like', 'pagos.%')->pluck('valor')))->not->toContain('sk_test_secreta')
        ->and(json_encode(RegistroAuditoria::query()->pluck('cambios')))->not->toContain('sk_test_secreta');

    $this->get('/motor-de-reservas')->assertOk()->assertSee('/reservar/posada');
});
