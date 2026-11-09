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
use App\Motor\MotorReservas;
use App\Pagos\CobrosEnLinea;
use App\Support\Ajustes;
use App\Support\Formato;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'personas_maximas' => 3]);
    foreach (['101', '102', '103'] as $i => $nombre) {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => $nombre, 'nombre' => $nombre, 'capacidad' => 3, 'orden' => $i + 1]);
    }
    $this->hoy = $this->propiedad->hoy();
    $this->formulario = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 5, 'unidades' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
});

afterEach(fn () => Ajustes::olvidar());

it('reparte a las personas lo más parejo posible', function () {
    expect(MotorReservas::reparto(5, 2))->toBe([3, 2])
        ->and(MotorReservas::reparto(6, 3))->toBe([2, 2, 2])
        ->and(MotorReservas::reparto(2, 4))->toBe([1, 1]);
});

it('busca y cotiza varias unidades juntas, hasta las que permite el motor', function () {
    $url = '/reservar/posada?llegada='.$this->hoy->addDays(5)->format('Y-m-d').'&salida='.$this->hoy->addDays(7)->format('Y-m-d').'&personas=5&unidades=2';

    // 2 noches en cada una: 3 personas pagan la tercera aparte si la tarifa la cobra; aquí no, así que son 4 noches.
    $this->get($url)->assertOk()->assertSee(trans_choice('motor.n_unidades', 2))->assertSee(Formato::dinero(400000, 'MXN'))->assertSee('name="unidades"', false);

    (new AjustesMotor($this->propiedad))->guardar(['unidades_maximas' => 1]);
    $this->get($url)->assertOk()->assertDontSee('name="unidades"', false)->assertDontSee(trans_choice('motor.n_unidades', 2));
});

it('crea una reserva por unidad, ligadas a la primera, y se pagan juntas', function () {
    $ajustes = new AjustesMotor($this->propiedad);
    $ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
    $ajustes->guardarLlave('stripe_webhook', 'whsec_x');

    $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
    $reservas = Reserva::query()->orderBy('id')->get();
    [$primera, $segunda] = [$reservas[0], $reservas[1]];

    expect($reservas)->toHaveCount(2)
        ->and($reservas->pluck('personas')->all())->toBe([3, 2])
        ->and($reservas->pluck('unidad_id')->unique())->toHaveCount(2)
        ->and($segunda->reserva_principal_id)->toBe($primera->id)
        ->and($primera->expira_en)->not->toBeNull()->and($segunda->expira_en)->not->toBeNull()
        ->and(app(CobrosEnLinea::class)->porCobrar($primera))->toBe(200000);

    // La de la segunda lleva a la primera, que muestra las dos.
    $this->get('/reservar/posada/r/'.$segunda->codigo_publico)->assertRedirectContains($primera->codigo_publico);
    $this->get('/reservar/posada/r/'.$primera->codigo_publico)->assertOk()->assertSee(__('motor.unidad_n', ['n' => 2]))->assertSee(Formato::dinero(400000, 'MXN'));

    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/1']),
        'api.stripe.com/v1/checkout/sessions/cs_test_1*' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 200000, 'currency' => 'mxn', 'payment_intent' => 'pi_1']),
    ]);
    $this->post('/reservar/posada/r/'.$primera->codigo_publico.'/pagar/stripe')->assertRedirect('https://checkout.stripe.test/1');
    Http::assertSent(fn ($r) => str_contains($r->url(), 'checkout/sessions') && ($r['line_items'][0]['price_data']['unit_amount'] ?? null) === 200000);
    $cobro = PagoEnLinea::query()->sole();
    $this->get('/reservar/posada/r/'.$primera->codigo_publico.'/retorno/stripe?cobro='.$cobro->id.'&session_id=cs_test_1')->assertRedirect();

    foreach ([$primera, $segunda] as $reserva) {
        $reserva->refresh();
        expect($reserva->pagado)->toBe(100000)
            ->and($reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($reserva->expira_en)->toBeNull();
    }
});

it('sin lugar para todas no aparta ninguna', function () {
    Unidad::query()->where('nombre', '103')->update(['activa' => false]);

    $this->post('/reservar/posada', ($this->formulario)(['unidades' => 3, 'personas' => 6]))->assertRedirectContains('aviso=sin_lugar');
    expect(Reserva::query()->count())->toBe(0);
});

it('en un pedido de varias no entra la casa completa: se asignan sus cuartos', function () {
    $casa = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => 'Casa', 'nombre' => 'Casa', 'capacidad' => 3, 'orden' => 0]);
    $casa->partes()->sync(Unidad::query()->whereIn('nombre', ['101', '102', '103'])->pluck('id')->all());

    $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
    expect(Reserva::query()->with('unidad')->get()->pluck('unidad.nombre')->sort()->values()->all())->toBe(['101', '102']);
});
