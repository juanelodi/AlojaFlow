<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoChoque;
use App\Enums\TipoReserva;
use App\Filament\Pages\Calendario;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Filament\Resources\Unidades\Schemas\UnidadForm;
use App\Ical\GestorIcal;
use App\Models\BloqueoExterno;
use App\Models\Choque;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Publico\CalendarioPublico;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Reservas\UnidadesVinculadas;
use App\Seguridad\Acceso;
use App\Servicios\Disponibilidad;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Villa', 'codigo' => 'villa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'calendario_publico' => true]]])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Noche', 'precio_noche' => 100000, 'personas_incluidas' => 8]);
    $unidad = fn (string $nombre, int $orden): Unidad => Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => $nombre, 'nombre' => $nombre, 'capacidad' => 8, 'orden' => $orden]);
    $this->casa = $unidad('Casa completa', 1);
    $this->h1 = $unidad('Cuarto 1', 2);
    $this->h2 = $unidad('Cuarto 2', 3);
    $this->h3 = $unidad('Cuarto 3', 4);
    $this->casa->partes()->sync([$this->h1->id, $this->h2->id, $this->h3->id]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->mes = CarbonImmutable::parse($this->propiedad->hoy()->addMonth()->format('Y-m-01'));
    $this->fecha = fn (int $dia): string => $this->mes->addDays($dia - 1)->format('Y-m-d');
    $this->reservar = fn (Unidad $unidad, int $desde, int $hasta): Reserva => app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => ($this->fecha)($desde), 'salida' => ($this->fecha)($hasta),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
    $this->gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
});

it('sabe con qué choca cada una: la casa con sus cuartos y con lo que comparte un cuarto', function (): void {
    $planta = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'Planta alta', 'nombre' => 'Planta alta', 'capacidad' => 4]);
    $planta->partes()->sync([$this->h1->id, $this->h2->id]);

    $mapa = UnidadesVinculadas::mapa([$this->casa->id, $this->h1->id, $this->h3->id, $planta->id]);
    $ordenados = fn (array $ids): array => collect($ids)->sort()->values()->all();

    expect($mapa[$this->casa->id])->toBe($ordenados([$this->h1->id, $this->h2->id, $this->h3->id, $planta->id]))
        ->and($mapa[$this->h1->id])->toBe($ordenados([$this->casa->id, $planta->id]))
        ->and($mapa[$this->h3->id])->toBe([$this->casa->id])
        ->and($mapa[$planta->id])->toBe($ordenados([$this->casa->id, $this->h1->id, $this->h2->id]))
        ->and(UnidadesVinculadas::conflictos($this->h2->id))->not->toContain($this->h3->id);
});

it('no deja vender la casa con un cuarto ocupado, ni un cuarto con la casa ocupada', function (): void {
    ($this->reservar)($this->h1, 5, 8);

    expect(fn () => ($this->reservar)($this->casa, 7, 10))->toThrow(ReservaRechazada::class, __('ui.reservas.error_vinculada', ['unidad' => 'Cuarto 1']));
    // Otro cuarto sí, y la casa en otras fechas también: el día de salida ya se puede vender.
    ($this->reservar)($this->h2, 5, 8);
    $casa = ($this->reservar)($this->casa, 8, 10);

    expect(fn () => ($this->reservar)($this->h3, 9, 11))->toThrow(ReservaRechazada::class);
    // Moverla tampoco se salta la regla.
    $libre = ($this->reservar)($this->h3, 12, 14);
    expect(fn () => app(GestorReservas::class)->mover($libre, $this->h3->id, CarbonImmutable::parse(($this->fecha)(9))))->toThrow(ReservaRechazada::class);
    // Cancelada, ya no cierra nada.
    app(GestorReservas::class)->cancelar($casa);
    expect(($this->reservar)($this->h3, 9, 11)->id)->toBeInt();
});

it('la búsqueda, el motor y el calendario público tampoco la ofrecen', function (): void {
    ($this->reservar)($this->h1, 5, 8);
    $fuente = FuenteIcal::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->h3->id, 'nombre' => 'Airbnb', 'url' => 'https://portal.example.test/x.ics']);
    BloqueoExterno::query()->create(['fuente_id' => $fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->h3->id, 'uid' => 'x', 'llegada' => ($this->fecha)(20), 'salida' => ($this->fecha)(22), 'primera_vez_en' => now(), 'visto_en' => now()]);
    $libres = fn (int $desde, int $hasta): array => Disponibilidad::libres([$this->propiedad->id], ($this->fecha)($desde), ($this->fecha)($hasta), 2)->pluck('nombre')->all();

    expect($libres(5, 8))->toBe(['Cuarto 2', 'Cuarto 3'])
        ->and($libres(20, 21))->toBe(['Cuarto 1', 'Cuarto 2'])
        ->and($libres(10, 12))->toBe(['Casa completa', 'Cuarto 1', 'Cuarto 2', 'Cuarto 3'])
        ->and(Disponibilidad::primeraLibre($this->propiedad->id, ($this->fecha)(5), ($this->fecha)(8), 2)?->nombre)->toBe('Cuarto 2');

    // Con la casa vendida no queda nada; con un cuarto vendido quedan los otros (y la casa no cierra los demás cuartos).
    ($this->reservar)($this->casa, 12, 14);
    $dia = fn (string $fecha): bool => collect((new CalendarioPublico($this->propiedad))->semanas($this->mes))->flatten(1)->filter()->first(fn (array $d): bool => $d['fecha']->format('Y-m-d') === $fecha)['libre'];
    expect($dia(($this->fecha)(12)))->toBeFalse()
        ->and($dia(($this->fecha)(5)))->toBeTrue()
        ->and($dia(($this->fecha)(15)))->toBeTrue();
});

it('el iCal de la casa cierra los días de sus cuartos en los portales', function (): void {
    ($this->reservar)($this->h2, 5, 8);
    $token = app(GestorIcal::class)->calendarioDe($this->casa)->enlace->token();

    expect($this->get(route('ical.exportar', ['token' => $token]))->assertOk()->getContent())
        ->toContain('DTSTART;VALUE=DATE:'.str_replace('-', '', ($this->fecha)(5)))
        ->toContain('DTEND;VALUE=DATE:'.str_replace('-', '', ($this->fecha)(8)));
});

it('el calendario dice quién ocupa la casa y no deja pulsar esos días', function (): void {
    ($this->reservar)($this->h1, 5, 8);

    Livewire::test(Calendario::class)->set('mes', $this->mes->format('Y-m'))
        ->assertSee(__('ui.calendario.vinculada', ['unidades' => 'Cuarto 1']))
        ->assertDontSee('?unidad='.$this->casa->id.'&amp;llegada='.($this->fecha)(5), false)
        ->assertSee('?unidad='.$this->casa->id.'&amp;llegada='.($this->fecha)(8), false);
});

it('desde la ficha de la unidad se eligen sus partes, de un solo nivel', function (): void {
    $patio = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'Patio', 'nombre' => 'Patio', 'capacidad' => 2]);

    Livewire::test(EditUnidad::class, ['record' => $this->casa->getRouteKey()])
        ->assertFormSet(['partes' => [$this->h1->id, $this->h2->id, $this->h3->id]])
        ->fillForm(['partes' => [$this->h1->id, $patio->id]])->call('save')->assertHasNoFormErrors();
    expect($this->casa->partes()->pluck('unidades.id')->sort()->values()->all())->toBe([$this->h1->id, $patio->id]);

    // Un cuarto ya es parte: no puede componerse de otras, aunque alguien mande el dato.
    Livewire::test(EditUnidad::class, ['record' => $this->h1->getRouteKey()])->assertFormFieldIsDisabled('partes');
    UnidadForm::guardarPartes($this->h1, [$this->h2->id]);
    // Ni la casa puede ser parte de otra.
    UnidadForm::guardarPartes($patio->refresh(), [$this->casa->id]);
    expect($this->h1->partes()->count())->toBe(0)
        ->and(DB::table('unidades_partes')->where('parte_id', $this->casa->id)->count())->toBe(0);
});

it('avisa como choque lo que ya estaba cuando se vincularon', function (): void {
    $this->casa->partes()->sync([]);
    ($this->reservar)($this->casa, 5, 8);
    ($this->reservar)($this->h1, 6, 7);
    $this->casa->partes()->sync([$this->h1->id]);

    expect(app(GestorIcal::class)->revisarChoques($this->propiedad))->toBe(1)
        ->and(Choque::query()->first()->tipo)->toBe(TipoChoque::ReservaReserva);
    expect(Reserva::query()->where('tipo', TipoReserva::Reserva->value)->count())->toBe(2);
});
