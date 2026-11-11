<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
 * Crear una reserva desde el teléfono: primero fechas y unidad, y lo poco usado, plegado.
 */
beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'orden' => 1, 'activa' => true]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->llegada = $this->propiedad->hoy()->addDays(10)->format('Y-m-d');
    $this->salida = $this->propiedad->hoy()->addDays(12)->format('Y-m-d');
    $this->recepcion = User::query()->create(['name' => 'Recepción', 'email' => 'recepcion@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->primeraLibre = TestAction::make('primeraLibre')->schemaComponent('unidad_id', schema: 'form');
});

it('pregunta primero las fechas y la unidad, y después lo demás', function () {
    $html = Livewire::actingAs($this->recepcion)->test(CreateReserva::class)->html();

    $posiciones = array_map(fn (string $campo): int|false => strpos($html, 'data.'.$campo), ['llegada', 'salida', 'personas', 'unidad_id', 'tipo', 'titular_id', 'total', 'estado', 'comentario']);

    expect($posiciones)->not->toContain(false);
    $ordenadas = $posiciones;
    sort($ordenadas);
    expect($posiciones)->toBe($ordenadas);
});

it('con una sola propiedad no la pregunta, y aun así guarda', function () {
    Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
        ->assertFormFieldHidden('propiedad_id')
        ->assertFormSet(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoReserva::Reserva->value, 'estado' => EstadoReserva::Confirmada->value])
        ->fillForm(['llegada' => $this->llegada, 'salida' => $this->salida, 'personas' => 2, 'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id])
        ->call('create')
        ->assertHasNoFormErrors();

    $reserva = Reserva::query()->firstOrFail();
    expect($reserva->propiedad_id)->toBe($this->propiedad->id)
        ->and($reserva->estado)->toBe(EstadoReserva::Confirmada)
        ->and($reserva->total)->toBe(200000);
});

it('con varias propiedades sí la pregunta', function () {
    $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion'], ['propiedad_id' => $otra->id, 'rol' => 'recepcion']]);

    Livewire::actingAs($this->recepcion)->test(CreateReserva::class)->assertFormFieldVisible('propiedad_id');
});

it('dice cuántas unidades quedan en cuanto hay fechas', function () {
    Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
        ->assertSee(__('ui.reservas.unidad_ayuda'))
        ->fillForm(['llegada' => $this->llegada, 'salida' => $this->salida])
        ->assertSee(trans_choice('ui.reservas.unidades_libres', 1, ['total' => 1]));
});

it('«primera libre» explica por qué no eligió nada', function () {
    $pagina = Livewire::actingAs($this->recepcion)->test(CreateReserva::class);

    $pagina->callAction($this->primeraLibre)->assertNotified(__('ui.reservas.primera_libre_sin_fechas'));

    $pagina->fillForm(['llegada' => $this->llegada, 'salida' => $this->salida])->callAction($this->primeraLibre)->assertFormSet(['unidad_id' => $this->unidad->id]);

    app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->llegada, 'salida' => $this->salida, 'personas' => 2, 'total' => 0,
    ]);
    $pagina->callAction($this->primeraLibre)->assertNotified(__('ui.reservas.primera_libre_ninguna'))->assertFormSet(['unidad_id' => null]);
});

it('un bloqueo sigue sin pedir huésped ni dinero', function () {
    Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
        ->fillForm(['tipo' => TipoReserva::Bloqueo->value])
        ->assertFormFieldHidden('titular_id')
        ->assertFormFieldHidden('total')
        ->assertFormFieldHidden('estado')
        ->fillForm(['llegada' => $this->llegada, 'salida' => $this->salida, 'unidad_id' => $this->unidad->id, 'origen_texto' => 'Pintura'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Reserva::query()->firstOrFail()->tipo)->toBe(TipoReserva::Bloqueo);
});

it('al crear va plegado lo poco usado y al editar se abre lo que tiene datos', function () {
    $plegadas = fn (string $html): int => (int) preg_match_all('/isCollapsed:\s*true/', $html);

    $alCrear = $plegadas(Livewire::actingAs($this->recepcion)->test(CreateReserva::class)->html());
    expect($alCrear)->toBeGreaterThanOrEqual(2);

    $reserva = app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->llegada, 'salida' => $this->salida, 'personas' => 2, 'total' => 150000, 'deposito' => 50000, 'comentario' => 'Llega de noche',
    ]);

    $html = Livewire::actingAs($this->recepcion)->test(EditReserva::class, ['record' => $reserva->getKey()])->assertOk()->html();
    expect($plegadas($html))->toBe($alCrear - 2)
        ->and($html)->toContain('Llega de noche');
});
