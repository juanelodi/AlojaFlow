<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30, 'paypal_cliente' => 'AbC-cliente', 'paypal_pruebas' => true]],
    ])->refresh();
    $ajustes = new AjustesMotor($this->propiedad);
    $ajustes->guardarLlave('paypal_secreto', 'secreto-de-prueba');
    $tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2]);
    $hoy = $this->propiedad->hoy();
    $this->post('/reservar/posada', [
        'tarifa_id' => $tarifa->id, 'llegada' => $hoy->addDays(5)->format('Y-m-d'), 'salida' => $hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ])->assertRedirect();
    $this->reserva = Reserva::query()->sole();
    $this->api = 'api-m.sandbox.paypal.com/';
    $this->orden = fn (string $estado, string $valor = '1000.00'): array => ['id' => 'ORDEN123', 'status' => 'COMPLETED', 'purchase_units' => [[
        'custom_id' => PagoEnLinea::query()->sole()->id.'-'.substr(hash_hmac('sha256', 'cobro:'.PagoEnLinea::query()->sole()->id, (string) config('app.key')), 0, 24),
        'payments' => ['captures' => [['id' => 'CAP1', 'status' => $estado, 'amount' => ['currency_code' => 'MXN', 'value' => $valor]]]],
    ]]];
});

afterEach(fn () => Ajustes::olvidar());

it('se ofrece en el motor y abre la orden con la referencia firmada', function () {
    Http::fake([
        $this->api.'v1/oauth2/token' => Http::response(['access_token' => 'A21-token', 'expires_in' => 3600]),
        $this->api.'v2/checkout/orders' => Http::response(['id' => 'ORDEN123', 'status' => 'PAYER_ACTION_REQUIRED', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDEN123']]], 201),
    ]);
    expect(array_keys((new Pasarelas($this->propiedad))->disponibles()))->toContain(Pasarelas::PAYPAL);
    $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico)->assertSee(__('motor.pagar_con.paypal'));

    $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/paypal')->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=ORDEN123');

    $cobro = PagoEnLinea::query()->sole();
    expect($cobro->referencia)->toBe('ORDEN123')->and($cobro->importe)->toBe(100000);
    Http::assertSent(fn (PeticionHttp $r) => $r->url() === 'https://'.$this->api.'v1/oauth2/token' && $r->hasHeader('Authorization', 'Basic '.base64_encode('AbC-cliente:secreto-de-prueba')));
    Http::assertSent(fn (PeticionHttp $r) => $r->url() === 'https://'.$this->api.'v2/checkout/orders'
        && $r['purchase_units'][0]['amount'] === ['currency_code' => 'MXN', 'value' => '1000.00']
        && str_starts_with($r['purchase_units'][0]['custom_id'], $cobro->id.'-')
        && $r->hasHeader('Authorization', 'Bearer A21-token'));
});

it('al volver captura la orden del cobro y anota el pago una sola vez', function () {
    Http::fake([
        $this->api.'v1/oauth2/token' => Http::response(['access_token' => 'A21-token']),
        $this->api.'v2/checkout/orders' => Http::response(['id' => 'ORDEN123', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDEN123']]], 201),
        $this->api.'v2/checkout/orders/ORDEN123/capture' => fn () => Http::response(($this->orden)('COMPLETED'), 201),
    ]);
    $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/paypal');
    $cobro = PagoEnLinea::query()->sole();

    // La orden que diga la dirección no cuenta: se usa la del cobro.
    $url = '/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/paypal?cobro='.$cobro->id.'&token=OTRA999&PayerID=X';
    $this->get($url)->assertRedirect();
    $this->get($url)->assertRedirect();

    $this->reserva->refresh();
    expect($this->reserva->pagado)->toBe(100000)->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada)->and($this->reserva->pagos()->count())->toBe(1)
        ->and($cobro->refresh()->estado)->toBe('pagado');
    Http::assertNotSent(fn (PeticionHttp $r) => str_contains($r->url(), 'OTRA999'));
});

it('con otro importe no da por pagada la reserva', function () {
    Http::fake([
        $this->api.'v1/oauth2/token' => Http::response(['access_token' => 'A21-token']),
        $this->api.'v2/checkout/orders' => Http::response(['id' => 'ORDEN123', 'links' => [['rel' => 'approve', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDEN123']]], 201),
        $this->api.'v2/checkout/orders/ORDEN123/capture' => fn () => Http::response(($this->orden)('COMPLETED', '1.00'), 201),
    ]);
    $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/paypal');
    $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/paypal?cobro='.PagoEnLinea::query()->sole()->id);

    expect($this->reserva->refresh()->pagado)->toBe(0);
});

it('el aviso de orden aprobada captura si la firma es válida; si no, no', function () {
    (new AjustesMotor($this->propiedad))->guardarLlave('paypal_webhook', 'WH123');
    $firma = 'SUCCESS';
    Http::fake([
        $this->api.'v1/oauth2/token' => Http::response(['access_token' => 'A21-token']),
        $this->api.'v2/checkout/orders' => Http::response(['id' => 'ORDEN123', 'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=ORDEN123']]], 201),
        $this->api.'v1/notifications/verify-webhook-signature' => function () use (&$firma) {
            return Http::response(['verification_status' => $firma]);
        },
        $this->api.'v2/checkout/orders/ORDEN123/capture' => fn () => Http::response(($this->orden)('COMPLETED'), 201),
    ]);
    $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/paypal');
    $aviso = ['event_type' => 'CHECKOUT.ORDER.APPROVED', 'resource' => ['id' => 'ORDEN123']];

    $firma = 'FAILURE';
    $this->postJson('/avisos-pago/posada/paypal', $aviso)->assertStatus(400);
    expect($this->reserva->refresh()->pagado)->toBe(0);

    $firma = 'SUCCESS';
    $this->postJson('/avisos-pago/posada/paypal', $aviso, ['PAYPAL-TRANSMISSION-ID' => 't1'])->assertOk();
    expect($this->reserva->refresh()->pagado)->toBe(100000);
    Http::assertSent(fn (PeticionHttp $r) => str_contains($r->url(), 'verify-webhook-signature') && $r['webhook_id'] === 'WH123' && $r['transmission_id'] === 't1');
});
