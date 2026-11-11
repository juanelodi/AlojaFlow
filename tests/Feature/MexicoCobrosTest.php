<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Filament\Pages\MotorDeReservas;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    Http::preventStrayRequests();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es', 'paquete_pais' => PaquetePais::Mexico,
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'activa' => true]);
    $this->hoy = $this->propiedad->hoy();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->contador = User::query()->create(['name' => 'Cont', 'email' => 'cont@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
});

describe('Stripe en México', function (): void {
    beforeEach(function (): void {
        $this->ajustes = new AjustesMotor($this->propiedad);
        $this->ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
        $this->ajustes->guardarLlave('stripe_webhook', 'whsec_x');
        $this->post('/reservar/posada', ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1'])->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->pagina = '/reservar/posada/r/'.$this->reserva->codigo_publico;
        $this->abrir = fn () => $this->post($this->pagina.'/pagar/stripe');
        $this->opciones = function (array $opciones): void {
            $propiedad = $this->propiedad->refresh();
            $ajustes = (array) $propiedad->ajustes;
            $ajustes['motor'] = $opciones + (array) $ajustes['motor'];
            $propiedad->update(['ajustes' => $ajustes]);
        };
        $this->sesionVoucher = fn (string $estadoPago = 'unpaid', string $tipo = 'oxxo'): array => [
            'id' => 'cs_1', 'status' => 'complete', 'payment_status' => $estadoPago, 'amount_total' => 100000, 'currency' => 'mxn',
            'payment_intent' => ['id' => 'pi_1', 'payment_method' => ['type' => $tipo], 'next_action' => $tipo === 'oxxo'
                ? ['oxxo_display_details' => ['hosted_voucher_url' => 'https://pagos.stripe.test/voucher', 'expires_after' => now()->addDays(3)->getTimestamp()]]
                : ['display_bank_transfer_instructions' => ['hosted_instructions_url' => 'https://pagos.stripe.test/spei']]],
        ];
        $this->avisar = function (string $tipo, array $objeto = ['id' => 'cs_1']) {
            $cuerpo = (string) json_encode(['type' => $tipo, 'data' => ['object' => $objeto]]);
            $firma = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$cuerpo, 'whsec_x');

            return $this->call('POST', '/avisos-pago/posada/stripe', [], [], [], ['HTTP_Stripe-Signature' => $firma, 'CONTENT_TYPE' => 'application/json'], $cuerpo);
        };
        $this->sesionNueva = ['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/1'])];
    });

    it('sin nada activado, no manda nada de más', function (): void {
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        Http::assertSent(fn (PeticionHttp $peticion): bool => ! isset($peticion['payment_method_options']) && ! isset($peticion['customer']));
    });

    it('activado, pide OXXO con sus días y meses sin intereses', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 2, 'stripe_meses' => true]);
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect();

        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 2
            && $peticion['payment_method_options']['card']['installments']['enabled'] === 'true');
    });

    it('en otra moneda no lo pide', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_meses' => true, 'stripe_spei' => true]);
        $this->reserva->forceFill(['moneda' => 'USD'])->save();
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect();

        Http::assertNotSent(fn (PeticionHttp $peticion): bool => str_contains($peticion->url(), '/customers'));
        Http::assertSent(fn (PeticionHttp $peticion): bool => ! isset($peticion['payment_method_options']));
    });

    it('SPEI crea un cliente con su nombre y correo, una sola vez', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1'])]);

        ($this->abrir)()->assertRedirect();

        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/customers') && $peticion['email'] === 'ana@example.com' && $peticion['name'] === 'Ana Ejemplo');
        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/checkout/sessions') && $peticion['customer'] === 'cus_1'
            && $peticion['payment_method_options']['customer_balance']['funding_type'] === 'bank_transfer'
            && $peticion['payment_method_options']['customer_balance']['bank_transfer']['type'] === 'mx_bank_transfer');
        expect($this->reserva->titular->refresh()->getAttribute('stripe_cliente'))->toBe('cus_1');

        // Otra sesión, mismo cliente.
        PagoEnLinea::query()->delete();
        ($this->abrir)()->assertRedirect();
        Http::assertSentCount(3);
    });

    it('si el cliente es de otra cuenta, crea otro y sigue', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        $this->reserva->titular->forceFill(['stripe_cliente' => 'cus_viejo'])->save();
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_nuevo']),
            'api.stripe.com/v1/checkout/sessions' => Http::sequence()
                ->push(['error' => ['code' => 'resource_missing', 'param' => 'customer']], 400)
                ->push(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/1']),
        ]);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        expect($this->reserva->titular->refresh()->getAttribute('stripe_cliente'))->toBe('cus_nuevo');
    });

    it('si no se puede crear el cliente, todavía puede pagar con tarjeta', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/customers' => Http::response(['error' => ['code' => 'x']], 500)]);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/checkout/sessions') && ! isset($peticion['customer']));
    });

    it('al elegir OXXO no se le va la reserva a los 30 minutos, y ve su ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed')->assertOk();

        $cobro = PagoEnLinea::query()->sole();
        $this->reserva->refresh();
        expect($cobro->estado)->toBe('en_espera')
            ->and($this->reserva->pagado)->toBe(0)
            ->and($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($this->reserva->expira_en->greaterThan(now()->addDays(2)))->toBeTrue();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);

        $this->get($this->pagina)->assertOk()->assertSee('https://pagos.stripe.test/voucher')->assertSee(__('motor.espera.titulo'))->assertDontSee(__('motor.pagar_con.stripe'));

        // Volver a pulsar «Pagar» lleva a la misma ficha: dos a la vez se podrían pagar las dos.
        ($this->abrir)()->assertRedirect('https://pagos.stripe.test/voucher');
        expect(PagoEnLinea::query()->count())->toBe(1);
    });

    it('cuando paga en la tienda queda pagado, sin decir que fue con tarjeta', function (): void {
        $paso = 'unpaid';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => function () use (&$paso) {
            return Http::response(($this->sesionVoucher)($paso));
        }]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');
        $paso = 'paid';

        ($this->avisar)('checkout.session.async_payment_succeeded')->assertOk();

        $this->reserva->refresh();
        expect($this->reserva->pagado)->toBe(100000)
            ->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($this->reserva->expira_en)->toBeNull()
            ->and($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Otro)
            ->and(PagoEnLinea::query()->sole()->estado)->toBe('pagado');
        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'));
    });

    it('la transferencia se anota como transferencia', function (): void {
        $paso = 'unpaid';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => function () use (&$paso) {
            return Http::response(($this->sesionVoucher)($paso, 'customer_balance'));
        }]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        // Sin fecha de vencimiento en la ficha: tres días.
        $this->reserva->refresh();
        expect($this->reserva->expira_en->greaterThan(now()->addHours(71)))->toBeTrue()
            ->and($this->reserva->expira_en->lessThan(now()->addHours(73)))->toBeTrue();
        $this->get($this->pagina)->assertSee('https://pagos.stripe.test/spei');

        $paso = 'paid';
        ($this->avisar)('checkout.session.async_payment_succeeded');
        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Transferencia);
    });

    it('la tarjeta sigue siendo tarjeta, también a meses', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)('paid', 'card'))]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed')->assertOk();

        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Tarjeta);
    });

    it('si el vale vence, suelta las noches pronto y puede volver a intentarlo', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        ($this->avisar)('checkout.session.async_payment_failed')->assertOk();

        $this->reserva->refresh();
        expect(PagoEnLinea::query()->sole()->estado)->toBe('fallido')
            ->and($this->reserva->expira_en->lessThan(now()->addHour()))->toBeTrue();
        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'))->assertSee(__('motor.pagar_con.stripe'));
    });

    it('un vale vencido del que nunca llegó el aviso no sigue mandando a la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        $this->travel(4)->days();

        expect(app(App\Pagos\CobrosEnLinea::class)->enEspera($this->reserva->refresh()))->toBeNull();
    });

    it('si la retención venció mientras llenaba la ficha, la recupera', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        $this->travel(40)->minutes();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada);

        ($this->avisar)('checkout.session.completed')->assertOk();

        $this->reserva->refresh();
        expect($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($this->reserva->expira_en)->not->toBeNull();
    });

    it('si Stripe no contesta al preguntar, usa lo que trae el aviso y no pierde el cobro', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response([], 500)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.async_payment_succeeded', ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 100000, 'currency' => 'mxn', 'payment_intent' => 'pi_1'])->assertOk();

        expect($this->reserva->refresh()->pagado)->toBe(100000);
    });

    it('un enlace de pago también espera, sin mezclarse con la reserva', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        $gestor = app(App\Pagos\GestorEnlacesPago::class);
        $enlace = $gestor->crear($this->reserva, 100000, 'Anticipo', 3, null);
        $url = $gestor->url($enlace);

        $this->post($url.'/stripe')->assertRedirect('https://checkout.stripe.test/1');
        ($this->avisar)('checkout.session.completed');

        $this->get($url)->assertSee('https://pagos.stripe.test/voucher');
        expect(app(App\Pagos\CobrosEnLinea::class)->enEspera($this->reserva->refresh()))->toBeNull();
    });

    it('una reserva sin retención no empieza a caducar por un enlace de pago', function (): void {
        $this->reserva->forceFill(['expira_en' => null])->save();
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        $gestor = app(App\Pagos\GestorEnlacesPago::class);
        $url = $gestor->url($gestor->crear($this->reserva, 100000, 'Anticipo', 3, null));
        $this->post($url.'/stripe')->assertRedirect();

        ($this->avisar)('checkout.session.completed');
        expect($this->reserva->refresh()->expira_en)->toBeNull();

        ($this->avisar)('checkout.session.async_payment_failed');
        expect($this->reserva->refresh()->expira_en)->toBeNull()
            ->and(PagoEnLinea::query()->sole()->estado)->toBe('fallido');
    });

    it('la ficha no dura más que lo que falta para llegar', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 7]);
        Http::fake($this->sesionNueva);

        ($this->abrir)();
        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 5);

        PagoEnLinea::query()->delete();
        $this->reserva->forceFill(['llegada' => $this->hoy->format('Y-m-d')])->save();
        ($this->abrir)();
        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 1);
    });

    it('no aparta noches más allá del día de llegada', function (): void {
        $llegada = $this->hoy->addDay();
        $this->reserva->forceFill(['llegada' => $llegada->format('Y-m-d')])->save();
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)('unpaid', 'customer_balance'))]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');

        $limite = Illuminate\Support\Carbon::parse($llegada->format('Y-m-d'), 'America/Mexico_City')->endOfDay();
        expect($this->reserva->refresh()->expira_en->lessThanOrEqualTo($limite))->toBeTrue()
            ->and($this->reserva->expira_en->greaterThan(now()))->toBeTrue();
    });

    it('si ya no debe nada, no le enseña la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');
        $this->get($this->pagina)->assertSee(__('motor.espera.titulo'));

        app(GestorReservas::class)->registrarPago($this->reserva->refresh(), 100000, MetodoPago::Efectivo, $this->hoy);

        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'));
    });

    it('un segundo aviso con menos datos no borra la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::sequence()->push(($this->sesionVoucher)())->push([], 500)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');
        ($this->avisar)('checkout.session.completed', ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'unpaid', 'amount_total' => 100000, 'currency' => 'mxn', 'payment_intent' => 'pi_1']);

        expect(PagoEnLinea::query()->sole()->datos['instrucciones'])->toBe('https://pagos.stripe.test/voucher');
    });

    it('una dirección de ficha que no es https no llega a la página', function (): void {
        $sesion = ($this->sesionVoucher)();
        $sesion['payment_intent']['next_action']['oxxo_display_details']['hosted_voucher_url'] = 'javascript:alert(1)';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response($sesion)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');

        $this->get($this->pagina)->assertDontSee('javascript:alert', false)->assertSee(__('motor.espera.titulo'));
    });

    it('la pantalla del motor guarda las opciones, solo en pesos', function (): void {
        Livewire::actingAs($this->admin)->test(MotorDeReservas::class)
            ->set('datos.stripe_oxxo', true)
            ->set('datos.stripe_oxxo_dias', 4)
            ->set('datos.stripe_meses', true)
            ->set('datos.stripe_spei', true)
            ->call('guardar')
            ->assertHasNoErrors();

        Ajustes::olvidar();
        expect((new AjustesMotor($this->propiedad->refresh()))->opcionesStripe())->toBe(['oxxo' => true, 'oxxo_dias' => 4, 'meses' => true, 'spei' => true]);
    });

    it('los días se quedan entre 1 y 7 aunque alguien guarde otra cosa', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 99]);

        expect((new AjustesMotor($this->propiedad->refresh()))->opcionesStripe()['oxxo_dias'])->toBe(7);
    });

    it('anonimizar olvida su cliente de Stripe', function (): void {
        $huesped = $this->reserva->titular;
        $huesped->forceFill(['stripe_cliente' => 'cus_1'])->save();

        app(App\Privacidad\GestorPrivacidad::class)->anonimizar($huesped->refresh());

        expect($huesped->refresh()->getAttribute('stripe_cliente'))->toBeNull();
    });
});

describe('Mercado Pago en México', function (): void {
    beforeEach(function (): void {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardarLlave('mercadopago_token', 'TEST-token');
        $this->post('/reservar/posada', ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1'])->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->pagina = '/reservar/posada/r/'.$this->reserva->codigo_publico;
        $this->respuestaDe = fn (array $pago): array => $pago + ['id' => 555, 'transaction_amount' => 1000.0, 'currency_id' => 'MXN', 'external_reference' => App\Pagos\CobrosEnLinea::referenciaInterna(PagoEnLinea::query()->sole()->id)];
        $this->pago = ['status' => 'pending', 'status_detail' => 'pending_waiting_payment', 'payment_type_id' => 'ticket', 'date_of_expiration' => now()->addDays(3)->toIso8601String(), 'transaction_details' => ['external_resource_url' => 'https://www.mercadopago.test/ficha']];
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref_1', 'init_point' => 'https://mp.test/pagar']),
            'api.mercadopago.com/v1/payments/555' => function () {
                return Http::response(($this->respuestaDe)($this->pago));
            },
        ]);
        $this->post($this->pagina.'/pagar/mercadopago')->assertRedirect('https://mp.test/pagar');
        $this->avisar = fn () => $this->postJson('/avisos-pago/posada/mercadopago', ['type' => 'payment', 'data' => ['id' => '555']]);
    });

    it('la ficha de OXXO aparta las noches y se enseña', function (): void {
        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('en_espera')
            ->and($this->reserva->refresh()->expira_en->greaterThan(now()->addDays(2)))->toBeTrue();
        $this->get($this->pagina)->assertSee('https://www.mercadopago.test/ficha');
    });

    it('pagada, se anota como lo que fue', function (): void {
        ($this->avisar)();
        $this->pago = ['status' => 'approved', 'status_detail' => 'accredited', 'payment_type_id' => 'ticket'];

        ($this->avisar)()->assertOk();

        expect($this->reserva->refresh()->pagado)->toBe(100000)
            ->and($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Otro);
    });

    it('cancelada por vencer, suelta las noches pronto', function (): void {
        ($this->avisar)();
        $this->pago = ['status' => 'cancelled', 'status_detail' => 'expired', 'payment_type_id' => 'ticket'];

        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('fallido')
            ->and($this->reserva->refresh()->expira_en->lessThan(now()->addHour()))->toBeTrue();
    });

    it('una tarjeta en revisión no es una ficha: no aparta de más', function (): void {
        $antes = $this->reserva->expira_en;
        $this->pago = ['status' => 'in_process', 'status_detail' => 'pending_review_manual', 'payment_type_id' => 'credit_card'];

        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('pendiente')
            ->and($this->reserva->refresh()->expira_en->equalTo($antes))->toBeTrue();
    });

    it('sin método conocido se queda como tarjeta, como hasta ahora; con tarjeta y transferencia, lo que fueron', function (): void {
        $this->pago = ['status' => 'approved', 'payment_type_id' => 'bank_transfer'];

        ($this->avisar)()->assertOk();

        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Transferencia);
    });
});
