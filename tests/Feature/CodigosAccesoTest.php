<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Comunicacion\VariablesMensaje;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Models\CodigoAcceso;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Publico\MiReserva;
use App\Reservas\CodigosAcceso;
use App\Seguridad\Acceso;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'Loft', 'nombre' => 'Loft', 'capacidad' => 2]);
    $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $hoy = $this->propiedad->hoy();
    $this->reserva = Reserva::query()->forceCreate(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'tipo' => TipoReserva::Reserva,
        'estado' => EstadoReserva::Confirmada, 'numero' => '1', 'codigo_publico' => 'abcde12345', 'llegada' => $hoy->addDays(3)->format('Y-m-d'), 'salida' => $hoy->addDays(5)->format('Y-m-d'),
        'moneda' => 'MXN', 'total' => 1000])->refresh();
    $this->recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
});

it('la reserva usa el suyo o, si no tiene, el fijo de la unidad, y se guarda cifrado', function (): void {
    CodigosAcceso::guardarDeUnidad($this->unidad, '4321');
    expect(CodigosAcceso::de($this->reserva))->toBe('4321');

    CodigosAcceso::guardar($this->reserva, '  98 76 ');
    expect(CodigosAcceso::de($this->reserva))->toBe('98 76')
        ->and(DB::table('codigos_acceso')->where('reserva_id', $this->reserva->id)->value('codigo'))->not->toContain('98')
        ->and(CodigoAcceso::query()->first()?->toArray())->not->toHaveKey('codigo')
        ->and(VariablesMensaje::de($this->reserva, 'es')['codigo_acceso'])->toBe('98 76');

    // La auditoría dice que cambió, no cuál es.
    expect(json_encode(RegistroAuditoria::query()->pluck('cambios')->all()))->not->toContain('9876')->not->toContain('98 76')->not->toContain('4321');

    CodigosAcceso::guardar($this->reserva, '');
    expect(CodigosAcceso::de($this->reserva))->toBe('4321')
        ->and(CodigosAcceso::generar())->toMatch('/^\d{6}$/');
});

it('el huésped lo ve desde el día antes de llegar y hasta que sale', function (): void {
    CodigosAcceso::guardar($this->reserva, '2468');
    $url = app(MiReserva::class)->urlDe($this->reserva, 'es');

    $this->get($url)->assertOk()->assertDontSee('2468');

    $this->travelTo(CarbonImmutable::parse($this->reserva->llegada->subDay()->format('Y-m-d').' 12:00', 'America/Mexico_City'));
    $this->get($url)->assertOk()->assertSee(__('portal.codigo_acceso'))->assertSee('2468');

    $this->travelTo(CarbonImmutable::parse($this->reserva->salida->addDay()->format('Y-m-d').' 12:00', 'America/Mexico_City'));
    $this->get($url)->assertOk()->assertDontSee('2468');
});

it('recepción lo escribe o lo genera desde la ficha, y la unidad guarda el fijo', function (): void {
    $this->actingAs($this->recepcion);

    Livewire::test(ViewReserva::class, ['record' => $this->reserva->getRouteKey()])
        ->callAction('codigoAcceso', ['codigo' => '1357'])->assertHasNoActionErrors()->assertNotified(__('ui.reservas.codigo_acceso_ok'));
    expect(CodigosAcceso::propio($this->reserva))->toBe('1357');

    $gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($gerente);
    Livewire::test(EditUnidad::class, ['record' => $this->unidad->getRouteKey()])
        ->fillForm(['codigo_acceso' => '0000'])->call('save')->assertHasNoFormErrors();
    expect(CodigosAcceso::deUnidad($this->unidad->id))->toBe('0000');
    Livewire::test(EditUnidad::class, ['record' => $this->unidad->getRouteKey()])->assertFormSet(['codigo_acceso' => '0000'])
        ->fillForm(['codigo_acceso' => null])->call('save')->assertHasNoFormErrors();
    expect(CodigosAcceso::deUnidad($this->unidad->id))->toBeNull();
});

it('quien solo ve los datos no lo cambia', function (): void {
    $contador = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
    $this->actingAs($contador);

    Livewire::test(ViewReserva::class, ['record' => $this->reserva->getRouteKey()])->assertActionHidden('codigoAcceso');
});
