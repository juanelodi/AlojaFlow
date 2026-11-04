<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Models\Aviso;
use App\Models\Cupon;
use App\Models\Huesped;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Motor\Pasarelas;
use App\Pagos\CobrosEnLinea;
use App\Pagos\ResultadoPago;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Support\Ajustes;
use App\Support\ClaveCliente;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    Ajustes::olvidar();
    RateLimiter::clear('motor-reserva');
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 0]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'orden' => 1]);
    $this->hoy = $this->propiedad->hoy();
    $this->fecha = fn (int $dias): string => $this->hoy->addDays($dias)->format('Y-m-d');
    $this->formulario = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => ($this->fecha)(5), 'salida' => ($this->fecha)(7), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
    $this->datos = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => ($this->fecha)(5), 'salida' => ($this->fecha)(7), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => null, 'comentario' => null, 'cupon' => null, 'cargos' => [], 'idioma' => 'es',
    ];
});

afterEach(fn () => Ajustes::olvidar());

describe('fechas absurdas', function () {
    it('no calcula rangos enormes: ni en la búsqueda, ni en el formulario, ni al reservar', function () {
        $absurda = '/reservar/posada?llegada='.($this->fecha)(1).'&salida=9999-12-31&personas=2';
        $inicio = microtime(true);
        $this->get($absurda)->assertOk()->assertSee(__('motor.error.fechas'));
        $this->get('/reservar/posada/datos?tarifa_id='.$this->tarifa->id.'&llegada='.($this->fecha)(1).'&salida=9999-12-31&personas=2')->assertRedirect();
        $this->post('/reservar/posada', ($this->formulario)(['salida' => '9999-12-31']));
        expect(microtime(true) - $inicio)->toBeLessThan(5.0)->and(Reserva::query()->count())->toBe(0);

        expect(Cotizador::cotizar($this->unidad, CarbonImmutable::parse(($this->fecha)(1)), CarbonImmutable::parse('9999-12-31'), 2))->toBeNull();
    });

    it('no acepta llegadas más allá del horizonte', function () {
        $lejana = $this->hoy->addDays(MotorReservas::DIAS_HORIZONTE + 1);
        $url = '/reservar/posada?llegada='.$lejana->format('Y-m-d').'&salida='.$lejana->addDay()->format('Y-m-d').'&personas=2';

        $this->get($url)->assertOk()->assertSee(__('motor.error.fecha_lejana'));
        expect(fn () => app(MotorReservas::class)->reservar($this->propiedad, ($this->datos)(['llegada' => $lejana->format('Y-m-d'), 'salida' => $lejana->addDay()->format('Y-m-d')])))
            ->toThrow(ReservaRechazada::class, __('motor.error.fecha_lejana'));
    });

    it('un parámetro de idioma que no es texto no rompe la página', function () {
        $this->get('/reservar/posada?idioma[]=x')->assertOk();
    });
});

describe('reservas anónimas', function () {
    it('caduca la reserva sin pago que nadie confirma y libera sus noches', function () {
        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datos)());
        expect($reserva->estado)->toBe(EstadoReserva::SinConfirmar)->and($reserva->expira_en)->not->toBeNull();

        $this->travel(47)->hours();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);

        $this->travel(2)->hours();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada)->and($reserva->nochesOcupadas()->count())->toBe(0);
    });

    it('la que confirma una persona ya no caduca; con 0 horas, tampoco la que espera', function () {
        $motor = app(MotorReservas::class);
        $confirmada = app(GestorReservas::class)->confirmar($motor->reservar($this->propiedad, ($this->datos)()));
        expect($confirmada->expira_en)->toBeNull();

        (new AjustesMotor($this->propiedad))->guardar(['horas_revision' => 0]);
        Ajustes::olvidar();
        $sinCaducar = $motor->reservar($this->propiedad->refresh(), ($this->datos)(['correo' => 'luz@example.com', 'llegada' => ($this->fecha)(20), 'salida' => ($this->fecha)(21)]));
        expect($sinCaducar->estado)->toBe(EstadoReserva::SinConfirmar)->and($sinCaducar->expira_en)->toBeNull();
    });

    it('limita las reservas vigentes de un mismo correo', function () {
        $motor = app(MotorReservas::class);
        foreach ([10, 20, 30] as $dias) {
            $motor->reservar($this->propiedad, ($this->datos)(['llegada' => ($this->fecha)($dias), 'salida' => ($this->fecha)($dias + 1)]));
        }

        expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['correo' => ' ANA@example.com', 'llegada' => ($this->fecha)(40), 'salida' => ($this->fecha)(41)])))
            ->toThrow(ReservaRechazada::class, __('motor.error.demasiadas'));
    });

    it('detiene el formulario si llegan demasiadas en una hora y avisa al alojamiento', function () {
        $motor = app(MotorReservas::class);
        Unidad::query()->whereKey($this->unidad->id)->update(['capacidad' => 2]);
        foreach (range(1, 30) as $i) {
            $motor->reservar($this->propiedad, ($this->datos)(['correo' => "h$i@example.com", 'llegada' => ($this->fecha)($i * 2), 'salida' => ($this->fecha)($i * 2 + 1)]));
        }

        expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['correo' => 'otra@example.com', 'llegada' => ($this->fecha)(90), 'salida' => ($this->fecha)(91)])))
            ->toThrow(ReservaRechazada::class, __('motor.error.saturado'));
        expect(Aviso::query()->where('tipo', 'motor_saturado')->whereNull('resuelto_en')->count())->toBe(1);
    });

    it('las reservas que carga el personal no detienen el motor', function () {
        $gestor = app(GestorReservas::class);
        foreach (range(1, 30) as $i) {
            $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => "H$i"]);
            $gestor->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'llegada' => ($this->fecha)($i * 2), 'salida' => ($this->fecha)($i * 2 + 1), 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000, 'cargos' => []]);
        }

        expect(app(MotorReservas::class)->reservar($this->propiedad, ($this->datos)(['llegada' => ($this->fecha)(90), 'salida' => ($this->fecha)(91)]))->exists)->toBeTrue();
    });

    it('reactivar quita la caducidad, y quien ya llegó no vence', function () {
        $motor = app(MotorReservas::class);
        $gestor = app(GestorReservas::class);
        $reactivada = $motor->reservar($this->propiedad, ($this->datos)());
        $enCasa = $motor->reservar($this->propiedad, ($this->datos)(['llegada' => $this->hoy->format('Y-m-d'), 'salida' => ($this->fecha)(3)]));
        $gestor->registrarEntrada($enCasa);

        $this->travel(49)->hours();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($reactivada->refresh()->estado)->toBe(EstadoReserva::Cancelada)->and($enCasa->refresh()->estado)->not->toBe(EstadoReserva::Cancelada);

        $gestor->reactivar($reactivada);
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($reactivada->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);
    });

    it('limita por dirección los envíos del formulario', function () {
        foreach (range(1, 5) as $i) {
            $this->post('/reservar/posada', ($this->formulario)(['correo' => "h$i@example.com", 'llegada' => ($this->fecha)($i * 3), 'salida' => ($this->fecha)($i * 3 + 1)]))->assertRedirect();
        }

        $this->post('/reservar/posada', ($this->formulario)(['correo' => 'otra@example.com', 'llegada' => ($this->fecha)(40), 'salida' => ($this->fecha)(41)]))->assertStatus(429);
    });

    it('rechaza el envío que viene de otro sitio, y acepta el del propio y el de quien no manda origen', function () {
        $this->post('/reservar/posada', ($this->formulario)(), ['Origin' => 'https://ajeno.example'])->assertForbidden();
        $this->post('/reservar/posada', ($this->formulario)(), ['Origin' => 'null'])->assertForbidden();
        expect(Reserva::query()->count())->toBe(0);

        $this->post('/reservar/posada', ($this->formulario)(), ['Origin' => url('/')])->assertRedirect();
        $this->post('/reservar/posada', ($this->formulario)(['correo' => 'luz@example.com', 'llegada' => ($this->fecha)(20), 'salida' => ($this->fecha)(21)]))->assertRedirect();
        expect(Reserva::query()->count())->toBe(2);
    });

    it('cuenta como un solo cliente toda la red /64 de IPv6', function () {
        $clave = fn (string $ip): string => ClaveCliente::de(Request::create('/', 'GET', server: ['REMOTE_ADDR' => $ip]));

        expect($clave('2001:db8:1:2::1'))->toBe($clave('2001:db8:1:2:ffff::9'))
            ->and($clave('2001:db8:1:3::1'))->not->toBe($clave('2001:db8:1:2::1'))
            ->and($clave('203.0.113.7'))->toBe('203.0.113.7');
    });
});

describe('cupones en el formulario público', function () {
    it('da el mismo mensaje exista o no el código', function () {
        Cupon::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'VERANO', 'tipo' => 'porcentaje', 'porcentaje' => 10, 'estancia_minima' => 30, 'activo' => true]);
        $motor = app(MotorReservas::class);

        expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['cupon' => 'NOEXISTE'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_no_valido'));
        expect(fn () => $motor->reservar($this->propiedad, ($this->datos)(['cupon' => 'VERANO'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_no_valido'));
    });
});

describe('cabeceras', function () {
    it('protege las páginas privadas y deja el motor dentro de un iframe', function () {
        $panel = $this->get('/login');
        $panel->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        expect($panel->headers->get('Content-Security-Policy'))->toContain("default-src 'self'")->toContain("frame-ancestors 'self'")->toContain("form-action 'self'");

        $motor = $this->get('/reservar/posada');
        $motor->assertOk()->assertHeaderMissing('X-Frame-Options')->assertHeaderMissing('X-Robots-Tag');
        expect($motor->headers->get('Content-Security-Policy'))->toContain('frame-ancestors *')->not->toContain('form-action');
    });

    it('la página de una reserva no se indexa', function () {
        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datos)());

        $this->get('/reservar/posada/r/'.$reserva->codigo_publico)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    });

    it('también van en las páginas de error', function () {
        $this->get('/no-existe')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    it('en producción con https manda a https y pide HSTS; sin https no hace nada', function () {
        config(['app.url' => 'https://hotel.example']);
        $this->app['env'] = 'production';

        $this->get('http://hotel.example/up')->assertRedirect('https://hotel.example/up')->assertStatus(301);
        $this->get('https://hotel.example/up')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        // Detrás de un proxy con https, sin bucle.
        $this->get('http://hotel.example/up', ['X-Forwarded-Proto' => 'https'])->assertOk();

        config(['app.url' => 'http://hotel.example']);
        $this->get('http://hotel.example/up')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    });
});

describe('pagos en línea', function () {
    beforeEach(function () {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardar(['anticipo_porcentaje' => 50, 'minutos_retencion' => 30]);
        $ajustes->guardarLlave('mercadopago_token', 'TEST-token');
        Ajustes::olvidar();
        $this->propiedad->refresh();
        $this->post('/reservar/posada', ($this->formulario)())->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->preferencias = 0;
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => function () {
                $this->preferencias++;

                return Http::response(['id' => 'pref_'.$this->preferencias, 'init_point' => 'https://mp.test/pagar/'.$this->preferencias]);
            },
            'api.mercadopago.com/v1/payments/*' => fn () => Http::response(['id' => 555, 'status' => 'approved', 'transaction_amount' => 1000.0, 'currency_id' => 'MXN', 'external_reference' => CobrosEnLinea::referenciaInterna(PagoEnLinea::query()->firstOrFail()->id)]),
        ]);
        $this->pagar = fn () => $this->post('/reservar/posada/r/'.$this->reserva->codigo_publico.'/pagar/mercadopago');
        $this->volver = fn () => $this->get('/reservar/posada/r/'.$this->reserva->codigo_publico.'/retorno/mercadopago?cobro='.PagoEnLinea::query()->firstOrFail()->id.'&payment_id=555&status=approved');
    });

    it('volver a pulsar pagar sigue en el mismo cobro', function () {
        ($this->pagar)()->assertRedirect('https://mp.test/pagar/1');
        ($this->pagar)()->assertRedirect('https://mp.test/pagar/1');
        expect(PagoEnLinea::query()->count())->toBe(1);

        $this->travel(25)->minutes();
        ($this->pagar)()->assertRedirect('https://mp.test/pagar/2');
    });

    it('rechaza el pago cuya referencia no lleva firma', function () {
        ($this->pagar)();
        $cobro = PagoEnLinea::query()->firstOrFail();
        $proveedor = (new Pasarelas($this->propiedad->refresh()))->proveedor('mercadopago');
        $pago = fn (string $referencia): ResultadoPago => new ResultadoPago($referencia, true, 100000, 'MXN', '777');

        foreach (['interna:'.$cobro->id, 'interna:'.$cobro->id.'-'.str_repeat('0', 24)] as $sinFirma) {
            expect(app(CobrosEnLinea::class)->confirmar($proveedor, $pago($sinFirma), $this->propiedad->id))->toBeNull();
        }
        expect($this->reserva->refresh()->pagado)->toBe(0);

        app(CobrosEnLinea::class)->confirmar($proveedor, $pago('interna:'.CobrosEnLinea::referenciaInterna($cobro->id)), $this->propiedad->id);
        expect($this->reserva->refresh()->pagado)->toBe(100000);
    });

    it('un pago que llega con la reserva cancelada por el personal no la reactiva', function () {
        ($this->pagar)();
        app(GestorReservas::class)->cancelar($this->reserva);

        ($this->volver)()->assertRedirect();

        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada)->and($this->reserva->pagado)->toBe(100000)
            ->and(Aviso::query()->where('tipo', 'pago_en_cancelada')->count())->toBe(1);
    });

    it('un pago que llega tras vencer la retención sí la recupera', function () {
        ($this->pagar)();
        $this->travel(31)->minutes();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada);

        ($this->volver)()->assertRedirect();

        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::Confirmada)->and(Aviso::query()->where('tipo', 'pago_en_cancelada')->count())->toBe(0);
    });

    it('avisa si un pago supera lo que se debía', function () {
        ($this->pagar)();
        // Mientras tanto, alguien cobró la reserva completa en recepción.
        app(GestorReservas::class)->registrarPago($this->reserva, 200000, App\Enums\MetodoPago::Efectivo, CarbonImmutable::now());

        ($this->volver)()->assertRedirect();

        expect(Aviso::query()->where('tipo', 'pago_duplicado')->count())->toBe(1)->and($this->reserva->refresh()->pagado)->toBe(300000);
    });
});
