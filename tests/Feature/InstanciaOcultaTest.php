<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Filament\Pages\AcercaDe;
use App\Filament\Pages\CopiasSeguridad;
use App\Filament\Pages\EstadoSistema;
use App\Models\Propiedad;
use App\Models\User;
use App\Modulos\PrimerosPasos;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Livewire\Livewire;

beforeEach(function () {
    $this->administrador = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
});

afterEach(fn () => Ajustes::olvidar());

it('en una instancia no se ofrece descargar el .env', function () {
    $this->actingAs($this->administrador);
    Livewire::test(CopiasSeguridad::class)->assertActionVisible('descargarEnv');

    comoInstancia();
    Livewire::test(CopiasSeguridad::class)->assertActionHidden('descargarEnv');
});

it('en una instancia, Estado del sistema no enseña el cron ni el PHP de consola', function () {
    $this->actingAs($this->administrador);
    $this->get(EstadoSistema::getUrl())->assertOk()->assertSee(__('ui.estado.cron_titulo'))->assertSee(__('ui.estado.programador'));

    comoInstancia();
    $this->get(EstadoSistema::getUrl())->assertOk()
        ->assertDontSee(__('ui.estado.cron_titulo'))
        ->assertDontSee(__('ui.estado.programador'))
        ->assertDontSee('schedule:run');
});

it('en una instancia, los primeros pasos no piden el cron', function () {
    comoInstancia();

    expect(collect(PrimerosPasos::de($this->administrador))->pluck('clave'))->not->toContain('cron');
});

it('en una instancia se quedan «Acerca de» y «Código fuente»', function () {
    comoInstancia();

    $this->actingAs($this->administrador)->get(AcercaDe::getUrl())->assertOk()->assertSee(route('codigo-fuente'));
});

it('quien no es administrador sigue sin ver Estado del sistema ni Copias', function () {
    comoInstancia();
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($gerente, [['propiedad_id' => $propiedad->id, 'rol' => 'gerente']]);

    $this->actingAs($gerente)->get(EstadoSistema::getUrl())->assertForbidden();
    $this->get(CopiasSeguridad::getUrl())->assertForbidden();
});
