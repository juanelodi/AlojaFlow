<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Demo\GeneradorDemo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Filament\Resources\Propiedades\Pages\ListPropiedades;
use App\Models\ContactoHuesped;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\User;
use App\Servicios\Operacion;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
});

it('crea una propiedad independiente con datos ficticios alrededor de hoy', function () {
    $propiedad = app(GeneradorDemo::class)->crear();
    $hoy = $propiedad->hoy()->format('Y-m-d');

    expect($propiedad->modo)->toBe(ModoPropiedad::Nativa)
        ->and($propiedad->es_demo)->toBeTrue()
        ->and($propiedad->principal)->toBeNull()
        ->and($propiedad->unidades()->count())->toBe(12)
        ->and($propiedad->tarifas()->count())->toBe(5)
        ->and($propiedad->huespedes()->count())->toBe(60);

    $resumen = Operacion::resumen($propiedad);
    expect($resumen['llegan'])->toBeGreaterThan(0)
        ->and($resumen['salen'])->toBeGreaterThan(0)
        ->and($resumen['alojados'])->toBeGreaterThan(0);

    $reservas = $propiedad->reservas();
    expect((clone $reservas)->where('tipo', TipoReserva::Bloqueo->value)->count())->toBeGreaterThan(0)
        ->and((clone $reservas)->where('estado', EstadoReserva::Cancelada->value)->count())->toBeGreaterThan(0)
        ->and((clone $reservas)->where('estado', EstadoReserva::SinConfirmar->value)->count())->toBeGreaterThan(0)
        ->and((clone $reservas)->where('sincronizada', true)->count())->toBe(0)
        ->and((clone $reservas)->whereDate('salida', '<', $hoy)->where('estado', EstadoReserva::Confirmada->value)->where('tipo', TipoReserva::Reserva->value)->whereNull('salida_registrada_en')->count())->toBe(0);

    // Cada reserva cobrada cuadra con sus pagos, y ninguna noche está repetida.
    $descuadradas = DB::table('reservas as r')->where('r.propiedad_id', $propiedad->id)
        ->whereRaw('r.pagado <> (SELECT COALESCE(SUM(p.importe), 0) FROM pagos p WHERE p.reserva_id = r.id)')->count();
    expect($descuadradas)->toBe(0);
    expect(DB::table('reservas_noches')->where('propiedad_id', $propiedad->id)->whereNull('exclusiva')->count())->toBe(0);

    // Sin correos reales ni actividad falsa en el historial.
    expect(ContactoHuesped::query()->where('tipo', 'correo')->where('valor', 'not like', '%@example.com')->count())->toBe(0)
        ->and(EventoReserva::query()->count())->toBe(0)
        ->and(RegistroAuditoria::query()->where('accion', 'like', 'reserva.%')->count())->toBe(0);
});

it('no crea dos y al quitarla no toca las demás propiedades', function () {
    $propia = Propiedad::query()->create(['nombre' => 'Real', 'codigo' => 'demo', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $huesped = Huesped::query()->create(['propiedad_id' => $propia->id, 'nombre' => 'Real']);

    $demo = app(GeneradorDemo::class)->crear();
    expect($demo->codigo)->toBe('demo-2');
    expect(fn () => app(GeneradorDemo::class)->crear())->toThrow(LogicException::class);

    expect(app(GeneradorDemo::class)->eliminar())->toBeTrue();
    expect(GeneradorDemo::existente())->toBeNull()
        ->and(Reserva::query()->count())->toBe(0)
        ->and(Propiedad::query()->whereKey($propia->id)->exists())->toBeTrue()
        ->and(Huesped::query()->pluck('id')->all())->toBe([$huesped->id]);
    expect(app(GeneradorDemo::class)->eliminar())->toBeFalse();
});

it('se crea y se quita con los botones de Propiedades y con el comando', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(ListPropiedades::class)
        ->assertActionVisible('crearDemo')->assertActionHidden('quitarDemo')
        ->callAction('crearDemo');
    expect(GeneradorDemo::existente())->not->toBeNull();

    Livewire::test(ListPropiedades::class)
        ->assertActionHidden('crearDemo')->assertActionVisible('quitarDemo')
        ->callAction('quitarDemo');
    expect(GeneradorDemo::existente())->toBeNull();

    $this->artisan('alojaflow:demo')->assertSuccessful();
    expect(GeneradorDemo::existente())->not->toBeNull();
    $this->artisan('alojaflow:demo')->assertFailed();
    $this->artisan('alojaflow:demo --quitar')->assertSuccessful();
    expect(GeneradorDemo::existente())->toBeNull();
});
