<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\ModoPropiedad;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Motor\AjustesMotor;
use App\Support\Ajustes;

/*
 * Lo que vive el huésped en el motor: errores que dicen qué hacer, sin repetir nada.
 */
beforeEach(function () {
    Ajustes::olvidar();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'descripcion_publica' => 'Cama matrimonial']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'orden' => 1]);
    $hoy = $this->propiedad->hoy();
    $this->hoy = $hoy;
    $this->fechas = 'llegada='.$hoy->addDays(5)->format('Y-m-d').'&salida='.$hoy->addDays(7)->format('Y-m-d');
    $this->formulario = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->tarifa->id, 'llegada' => $hoy->addDays(5)->format('Y-m-d'), 'salida' => $hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
});

afterEach(fn () => Ajustes::olvidar());

it('dice en cada campo qué falta y qué hacer, y no le hace repetir nada', function () {
    $this->post('/reservar/posada?idioma=es', ['correo' => 'noesuncorreo', 'telefono' => '123', 'comentario' => 'Llego tarde'] + ($this->formulario)())
        ->assertStatus(422)
        // Lo escrito sigue ahí, también la casilla.
        ->assertSee('value="noesuncorreo"', false)
        ->assertSee('Llego tarde')
        ->assertSee('id="acepto" name="acepto" value="1" required checked', false)
        ->assertSee(__('motor.validacion.correo_formato'))
        ->assertSee(__('motor.validacion.telefono_formato'))
        ->assertSee('aria-describedby="error-correo"', false)
        ->assertSee('id="error-telefono"', false)
        ->assertSee('href="#correo"', false)
        // Vuelve abierta en el formulario, no arriba de la página.
        ->assertSee('#datos"', false);

    expect(Reserva::query()->count())->toBe(0);
});

it('pide la casilla con palabras claras y no la marca sola', function () {
    $datos = ($this->formulario)();
    unset($datos['acepto']);

    $this->post('/reservar/posada?idioma=es', $datos)->assertStatus(422)
        ->assertSee(__('motor.validacion.acepto'))
        ->assertDontSee('value="1" required checked', false);
});

it('acepta un teléfono normal y rechaza el que no alcanza para localizar a nadie', function () {
    $this->post('/reservar/posada', ($this->formulario)(['telefono' => '55 50-01']))->assertStatus(422);
    expect(Reserva::query()->count())->toBe(0);

    $this->post('/reservar/posada', ($this->formulario)(['telefono' => '(55) 5550-0101']))->assertRedirect();
    expect(Reserva::query()->count())->toBe(1);
});

it('un cupón malo dice qué hacer, y queda a la vista lo que se escribió', function () {
    $html = (string) $this->post('/reservar/posada?idioma=es', ($this->formulario)(['cupon' => 'NOEXISTE']))->assertStatus(422)
        ->assertSee(__('motor.error.cupon_que_hacer'))
        ->assertSee('value="NOEXISTE"', false)
        ->getContent();

    expect($html)->toMatch('/<details class="filtros"\s+open/');
});

it('lleva los autocompletados correctos para que el teléfono llene los datos', function () {
    $this->get('/reservar/posada/datos?idioma=es&personas=2&tarifa='.$this->tarifa->id.'&'.$this->fechas)
        ->assertOk()
        ->assertSee('autocomplete="given-name"', false)
        ->assertSee('autocomplete="family-name"', false)
        ->assertSee('autocomplete="email"', false)
        ->assertSee('autocomplete="tel"', false)
        ->assertSee('autocapitalize="none"', false)
        ->assertSee('aria-current="step"', false);
});

it('dice una persona, no «1 personas», y explica qué probar si no hay lugar', function () {
    $this->unidad->update(['capacidad' => 1]);

    $this->get('/reservar/posada?idioma=es&personas=1&'.$this->fechas)->assertOk()->assertSee(trans_choice('motor.capacidad', 1))->assertDontSee('1 personas');
    $this->get('/reservar/posada/datos?idioma=es&personas=1&tarifa='.$this->tarifa->id.'&'.$this->fechas)->assertOk()->assertDontSee('1 personas');
    $this->get('/reservar/posada?idioma=es&personas=9&'.$this->fechas)->assertOk()->assertSee(__('motor.sin_resultados'));
});

it('conserva el idioma al devolver a la búsqueda porque ya no hay lugar', function () {
    $this->get('/reservar/posada/datos?idioma=fr&personas=2&tarifa=999999&'.$this->fechas)->assertRedirectContains('idioma=fr');
    $this->get('/reservar/posada/datos?idioma=fr&personas=9&tarifa='.$this->tarifa->id.'&'.$this->fechas)
        ->assertRedirectContains('idioma=fr')
        ->assertRedirectContains('aviso=sin_lugar');
});

it('el formulario y los resultados van antes que la descripción', function () {
    $this->propiedad->update(['pagina_publica' => ['descripcion' => 'Frente al mar']]);

    $html = (string) $this->get('/reservar/posada?idioma=es&personas=2&'.$this->fechas)->assertOk()->getContent();

    // strrpos: la descripción también va en los datos estructurados de la cabecera.
    expect(strpos($html, 'name="llegada"'))->toBeLessThan(strrpos($html, 'Frente al mar'))
        ->and(strpos($html, 'Cama matrimonial'))->toBeLessThan(strrpos($html, 'Frente al mar'));
});

it('la fecha inicial respeta los días de antelación', function () {
    (new AjustesMotor($this->propiedad))->guardar(['dias_antelacion' => 3]);
    $primerDia = $this->hoy->addDays(3)->format('Y-m-d');

    $this->get('/reservar/posada?idioma=es')->assertOk()->assertSee('min="'.$primerDia.'"', false)->assertSee('value="'.$primerDia.'"', false);
});

it('avisa si en el siguiente paso se paga, y en la confirmación pone primero el pago', function () {
    $sinPago = '/reservar/posada/datos?idioma=es&personas=2&tarifa='.$this->tarifa->id.'&'.$this->fechas;
    $this->get($sinPago)->assertOk()->assertSee(__('motor.sin_cobro'));

    $ajustes = new AjustesMotor($this->propiedad);
    $ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
    $ajustes->guardarLlave('stripe_webhook', 'whsec_x');
    $this->get($sinPago)->assertOk()->assertSee(__('motor.sin_cobro_pago'));

    $this->post('/reservar/posada?idioma=es', ($this->formulario)())->assertRedirect();
    $reserva = Reserva::query()->firstOrFail();

    $html = (string) $this->get('/reservar/posada/r/'.$reserva->codigo_publico.'?idioma=es')->assertOk()
        ->assertSee(__('motor.guardar_enlace'))
        ->getContent();

    expect(strpos($html, 'class="pagos"'))->toBeLessThan(strpos($html, $reserva->codigo_publico.'</h2>'));
});

it('las personas elegidas pasan por el calendario hasta la búsqueda', function () {
    (new AjustesMotor($this->propiedad))->guardar(['calendario_publico' => true]);

    $this->get('/reservar/posada?idioma=es&personas=3')->assertOk()->assertSee('personas=3', false);
    $this->get('/reservar/posada/calendario?idioma=es&personas=3')->assertOk()->assertSee('personas=3', false);
});
