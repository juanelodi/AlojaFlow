<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\CierreDia as PaginaCierre;
use App\Filament\Pages\PlanoPropiedad;
use App\Filament\Resources\ColoresTipoCliente\Pages\CreateColorTipoCliente;
use App\Limpieza\GestorLimpieza;
use App\Models\CierreDia;
use App\Models\ColorTipoCliente;
use App\Models\Huesped;
use App\Models\Plano;
use App\Models\PlanoUnidad;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Servicios\Calendario;
use App\Servicios\CierreDelDia;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2, 'activa' => true]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana']);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (int $desde, int $hasta) => app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
        'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

it('resume el día y guarda la foto al cerrar', function () {
    $reserva = ($this->reservar)(0, 1);
    app(GestorReservas::class)->registrarPago($reserva, 40000, MetodoPago::Efectivo, $this->hoy);
    $servicio = app(CierreDelDia::class);

    $resumen = $servicio->resumen($this->propiedad, $this->hoy);
    expect($resumen['llegadas_sin_registrar'])->toBe(1)
        ->and($resumen['ocupadas'])->toBe(1)
        ->and($resumen['unidades'])->toBe(1)
        ->and($resumen['cobros'])->toBe(['efectivo' => 40000]);
    expect($servicio->resumen($this->propiedad, $this->hoy->addDay())['por_cobrar_salidas'])->toBe(60000);

    Livewire::test(PaginaCierre::class)->assertSee(__('cierre.llegadas_sin_registrar'))
        ->callAction(TestAction::make('cerrar')->arguments(['propiedad' => $this->propiedad->id]), ['notas' => 'Todo bien'])
        ->assertHasNoActionErrors()->assertSee('Todo bien');

    // Volver a cerrar actualiza la foto, sin duplicar.
    app(GestorReservas::class)->registrarEntrada($reserva->refresh());
    $servicio->cerrar($this->propiedad, $this->hoy, 'Corregido');
    expect(CierreDia::query()->count())->toBe(1)
        ->and(CierreDia::query()->first()->resumen['llegadas_sin_registrar'])->toBe(0)
        ->and(CierreDia::query()->first()->notas)->toBe('Corregido');
});

it('crea el plano con las habitaciones y enseña su estado de hoy', function () {
    ($this->reservar)(-1, 2);

    Livewire::test(PlanoPropiedad::class)->assertSee(__('plano.vacio'))
        ->callAction('nuevo', ['propiedad' => $this->propiedad->id, 'nombre' => 'Planta baja'])
        ->assertHasNoActionErrors()->assertSet('editando', true)->assertSee('Cuarto 101')->assertSee(__('plano.estados.ocupada'));

    $plano = Plano::query()->firstOrFail();
    expect($plano->posiciones()->count())->toBe(1);

    Livewire::test(PlanoPropiedad::class)->call('moverUnidad', $this->unidad->id, 150.0, 30.5);
    $posicion = PlanoUnidad::query()->firstOrFail();
    expect((float) $posicion->x)->toBe(100.0)->and((float) $posicion->y)->toBe(30.5);

    app(GestorLimpieza::class)->cambiar($this->unidad, fueraDeServicio: true);
    Livewire::test(PlanoPropiedad::class)->assertSee(__('limpieza.fuera_de_servicio'));
});

it('quien no tiene el permiso ve el plano, pero no lo cambia', function () {
    $plano = Plano::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'P']);
    PlanoUnidad::query()->create(['plano_id' => $plano->id, 'unidad_id' => $this->unidad->id, 'x' => 10, 'y' => 10]);
    $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $this->actingAs($limpieza);

    Livewire::test(PlanoPropiedad::class)->assertSee('Cuarto 101')->assertActionHidden('editar')
        ->call('moverUnidad', $this->unidad->id, 50.0, 50.0)->assertForbidden();
    expect((float) PlanoUnidad::query()->first()->x)->toBe(10.0);
});

it('pinta en el calendario según la ficha del huésped', function () {
    ($this->reservar)(0, 1);
    $this->huesped->campos()->create(['campo' => 'Tipo de cliente', 'valor' => 'Empresa']);

    Livewire::test(CreateColorTipoCliente::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'campo' => 'Tipo de cliente', 'valor' => 'empresa', 'color' => '#ff0000'])
        ->call('create')->assertHasNoFormErrors();
    expect(ColorTipoCliente::query()->count())->toBe(1);

    $mes = Calendario::mes([$this->propiedad->id], $this->hoy->startOfMonth());
    expect($mes['filas'][0]['barras'][0]['color'])->toBe('#ff0000');
});
