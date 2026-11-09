<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Filament\Pages\Hoy;
use App\Models\MarcaEstancia;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->sub = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN'])->refresh();
    $unidad = Unidad::query()->forceCreate(['propiedad_id' => $this->sub->id, 'codigo' => '1', 'nombre' => 'Uno', 'sincronizada' => true]);
    $this->reserva = Reserva::query()->forceCreate(['propiedad_id' => $this->sub->id, 'unidad_id' => $unidad->id, 'tipo' => TipoReserva::Reserva, 'estado' => EstadoReserva::Confirmada,
        'numero' => '1', 'codigo_publico' => 'abcde12345', 'llegada' => $this->sub->hoy()->format('Y-m-d'), 'salida' => $this->sub->hoy()->addDay()->format('Y-m-d'), 'moneda' => 'MXN', 'total' => 1000, 'sincronizada' => true]);
    $this->recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->sub->id, 'rol' => 'recepcion']]);
});

it('en una subordinada registra la llegada y la salida con una marca propia, sin tocar la reserva de la principal', function () {
    $this->actingAs($this->recepcion);
    $hoy = Livewire::test(Hoy::class);

    $hoy->call('registrarEntrada', $this->reserva->id)->assertNotified(__('ui.reservas.entrada_ok'));
    $marca = MarcaEstancia::query()->where('reserva_id', $this->reserva->id)->firstOrFail();
    expect($marca->entrada_en)->not->toBeNull()
        ->and($marca->entrada_por)->toBe($this->recepcion->id)
        ->and($this->reserva->refresh()->entrada_registrada_en)->toBeNull();

    $hoy->call('registrarEntrada', $this->reserva->id)->assertNotified(__('ui.reservas.error_entrada'));
    $hoy->call('registrarSalida', $this->reserva->id)->assertNotified(__('ui.reservas.salida_ok'));
    expect($marca->refresh()->salida_en)->not->toBeNull();
});

it('Hoy dice cuántos ya llegaron y la hora, también en una subordinada', function () {
    MarcaEstancia::query()->create(['reserva_id' => $this->reserva->id, 'entrada_en' => now('UTC')]);

    $this->actingAs($this->recepcion)->get(Hoy::getUrl())->assertOk()
        ->assertSee(__('ui.hoy.ya_llegan', ['hechas' => 1, 'total' => 1]))
        ->assertSee(__('ui.comun.en_casa').' · '.Formato::hora(now(), 'America/Mexico_City'));
});

it('si la principal ya registró la llegada, aquí solo se anota la salida', function () {
    DB::table('reservas')->where('id', $this->reserva->id)->update(['entrada_registrada_en' => now('UTC')->subHours(3)]);
    $this->actingAs($this->recepcion);
    $hoy = Livewire::test(Hoy::class);

    $hoy->call('registrarEntrada', $this->reserva->id)->assertNotified(__('ui.reservas.error_entrada'));
    $hoy->call('registrarSalida', $this->reserva->id)->assertNotified(__('ui.reservas.salida_ok'));
    expect(MarcaEstancia::query()->firstOrFail()->entrada_en)->toBeNull()
        ->and($this->reserva->refresh()->salidaEn())->not->toBeNull();
});

it('sin permiso de reservas en esa propiedad no se registra nada', function () {
    $otro = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($otro);

    expect(fn () => Livewire::test(Hoy::class)->call('registrarEntrada', $this->reserva->id))->toThrow(Exception::class);
    expect(MarcaEstancia::query()->count())->toBe(0);
});
