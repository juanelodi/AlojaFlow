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
use App\Filament\Pages\Calendario;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Servicios\Calendario as ServicioCalendario;
use App\Support\ColoresEstado;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '1', 'nombre' => 'Uno', 'orden' => 1]);
    $this->reserva = fn (array $datos): Reserva => Reserva::query()->forceCreate($datos + [
        'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => TipoReserva::Reserva, 'estado' => EstadoReserva::Confirmada,
        'numero' => (string) random_int(1, 99999), 'codigo_publico' => bin2hex(random_bytes(5)), 'llegada' => '2030-03-10', 'salida' => '2030-03-12', 'moneda' => 'MXN',
        'total' => 100000, 'pagado' => 0, 'deposito' => 30000,
    ]);
});

it('pone cada reserva en su grupo de pago', function () {
    $pago = fn (array $datos): ?string => ServicioCalendario::pago(($this->reserva)($datos));

    expect($pago(['pagado' => 100000]))->toBe('total')
        ->and($pago(['pagado' => 30000]))->toBe('anticipo')
        ->and($pago(['pagado' => 10000]))->toBe('sin-anticipo')
        ->and($pago(['pagado' => 0, 'deposito' => 0]))->toBe('sin-anticipo')
        ->and($pago(['pagado' => 100000, 'estado' => EstadoReserva::SinConfirmar]))->toBe('sin-confirmar')
        ->and($pago(['tipo' => TipoReserva::Bloqueo]))->toBeNull();
});

it('el calendario dice de cuándo es y muestra lo pagado solo a quien ve los datos', function () {
    CarbonImmutable::setTestNow('2030-03-01 14:05:00');
    ($this->reserva)(['pagado' => 30000]);
    $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $limpieza = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $hora = Formato::hora(CarbonImmutable::now('America/Mexico_City'));

    $this->actingAs($admin)->get(Calendario::getUrl(['mes' => '2030-03']))->assertOk()
        ->assertSee(__('ui.calendario.situacion', ['hora' => $hora, 'fecha' => Formato::fechaNumerica(CarbonImmutable::now('America/Mexico_City'), true)]))
        ->assertSee('af-barra--confirmada af-pago--anticipo')
        ->assertSee(__('ui.calendario.pagado_de', ['pagado' => Formato::dinero(30000, 'MXN'), 'total' => Formato::dinero(100000, 'MXN')]))
        ->assertSee('data-af-cal-opcion="fijar-dias"', false);

    // Limpieza ve el calendario, pero no los datos de la reserva.
    $this->actingAs($limpieza)->get(Calendario::getUrl(['mes' => '2030-03']))->assertOk()
        ->assertDontSee('af-barra--confirmada af-pago--anticipo')
        ->assertDontSee(__('ui.calendario.colorear_pago'))
        ->assertDontSee(Formato::dinero(30000, 'MXN'));
    CarbonImmutable::setTestNow();
});

it('en una subordinada dice de cuándo es la copia de la principal', function () {
    $this->propiedad->forceFill(['modo' => ModoPropiedad::Subordinada])->save();
    Principal::query()->forceCreate(['propiedad_id' => $this->propiedad->id, 'url_base' => 'https://hd.example.test', 'usuario' => 'u', 'contrasena' => 'x', 'ultima_correcta_en' => '2030-03-01 13:50:00']);
    $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $copia = CarbonImmutable::parse('2030-03-01 13:50:00', 'UTC')->setTimezone('America/Mexico_City');

    $this->actingAs($admin)->get(Calendario::getUrl(['mes' => '2030-03']))->assertOk()
        ->assertSee(__('ui.calendario.copia_principal', ['hora' => Formato::hora($copia), 'fecha' => Formato::fechaNumerica($copia, true)]));
});

it('los colores por pago de cada usuario solo se aplican al colorear por pago', function () {
    $usuario = User::factory()->create(['colores_estado' => ['pago_total' => '#123456', 'pago_anticipo' => 'nada']]);

    expect(ColoresEstado::css($usuario))->toBe('html[data-af-cal-colores="pago"] .af-pago--total{background:#123456;color:#ffffff}');
});
