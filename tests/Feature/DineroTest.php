<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Dinero\GestorPoliticas;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoTarea;
use App\Filament\Pages\Informes as PaginaInformes;
use App\Filament\Resources\Politicas\Pages\CreatePolitica;
use App\Models\Canal;
use App\Models\Huesped;
use App\Models\MarcaNoShow;
use App\Models\Movimiento;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Servicios\Informes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'paquete_pais' => PaquetePais::Mexico, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo', 'nacionalidad_texto' => 'México']);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (int $desde, int $hasta, int $total = 116000, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => $total,
    ]);
    $this->politicas = app(GestorPoliticas::class);
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

describe('políticas', function () {
    beforeEach(function () {
        $this->politica = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Flexible', 'dias_cancelacion_gratuita' => 3, 'penalizacion_porcentaje' => 50, 'no_show_porcentaje' => 100, 'deposito_porcentaje' => 30, 'predeterminada' => true]);
    });

    it('calcula la penalización según los días que faltan', function () {
        $lejos = ($this->reservar)(5, 6, 100000);
        $cerca = ($this->reservar)(1, 2, 100000);

        expect($this->politicas->penalizacionCancelacion($lejos->load('propiedad'), $this->politica))->toBe(0)
            ->and($this->politicas->penalizacionCancelacion($cerca->load('propiedad'), $this->politica))->toBe(50000)
            ->and($this->politicas->depositoPendiente($cerca, $this->politica))->toBe(30000);
    });

    it('la política del canal va antes que la predeterminada', function () {
        $otra = Politica::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Portal', 'penalizacion_porcentaje' => 100]);
        Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Airbnb', 'politica_id' => $otra->id]);

        expect($this->politicas->aplicable(($this->reservar)(1, 2, 1000, ['origen_texto' => 'Airbnb']))->nombre)->toBe('Portal')
            ->and($this->politicas->aplicable(($this->reservar)(3, 4, 1000, ['origen_texto' => 'Directo']))->nombre)->toBe('Flexible');
    });

    it('al cancelar dice cuánto se devuelve', function () {
        $reserva = ($this->reservar)(1, 2, 100000);
        app(GestorReservas::class)->registrarPago($reserva, 80000, MetodoPago::Efectivo, $this->hoy);

        expect($this->politicas->resumenCancelacion($reserva->refresh()->load('propiedad')))->toContain('Flexible')->toContain('$500.00')->toContain('$800.00')->toContain('$300.00');
    });

    it('marca el no-show: libera las noches en una independiente y solo el día de llegada o después', function () {
        $futura = ($this->reservar)(2, 3, 100000);
        expect(fn () => $this->politicas->marcarNoShow($futura->load('propiedad'), null))->toThrow(ReservaRechazada::class);

        $hoy = ($this->reservar)(0, 1, 100000);
        $marca = $this->politicas->marcarNoShow($hoy->load('propiedad'), 'No contestó');
        expect($marca->penalizacion)->toBe(100000)
            ->and($hoy->refresh()->estado)->toBe(EstadoReserva::Cancelada)
            ->and($hoy->nochesOcupadas()->count())->toBe(0)
            ->and($this->politicas->puedeMarcarNoShow($hoy->refresh()->load('propiedad')))->toBeFalse();
    });

    it('en una subordinada el no-show no toca la reserva', function () {
        $sub = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'UTC', 'moneda' => 'MXN'])->refresh();
        $unidad = Unidad::query()->forceCreate(['propiedad_id' => $sub->id, 'codigo' => '1', 'nombre' => 'Uno', 'sincronizada' => true]);
        $reserva = Reserva::query()->forceCreate(['propiedad_id' => $sub->id, 'unidad_id' => $unidad->id, 'tipo' => App\Enums\TipoReserva::Reserva, 'estado' => EstadoReserva::Confirmada, 'numero' => '1', 'codigo_publico' => 'abcde12345', 'llegada' => $sub->hoy()->format('Y-m-d'), 'salida' => $sub->hoy()->addDay()->format('Y-m-d'), 'moneda' => 'MXN', 'total' => 1000, 'sincronizada' => true]);

        $this->politicas->marcarNoShow($reserva->load('propiedad'), null);

        expect($reserva->refresh()->estado)->toBe(EstadoReserva::Confirmada)->and(MarcaNoShow::query()->count())->toBe(1);
    });

    it('se crea desde el panel', function () {
        Livewire::test(CreatePolitica::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Estricta', 'dias_cancelacion_gratuita' => 7, 'penalizacion_porcentaje' => 100, 'no_show_porcentaje' => 100, 'deposito_porcentaje' => 50, 'deposito_plazo_dias' => 2])
            ->call('create')->assertHasNoFormErrors();
        expect(Politica::query()->where('nombre', 'Estricta')->exists())->toBeTrue();
    });
});

describe('informes', function () {
    beforeEach(function () {
        $this->rango = [$this->hoy->startOfMonth()->subMonth(), $this->hoy->endOfMonth()->addMonth()];
        $this->generar = fn (string $informe): array => Informes::generar($informe, [$this->propiedad->id], $this->rango[0], $this->rango[1]);
    });

    it('el CSV no deja pasar fórmulas', function () {
        expect(App\Support\Csv::fila(['=HYPERLINK("x")', '+1', '@a', 'Ana', '-500', -500, '1000.50', null]))
            ->toBe(["'=HYPERLINK(\"x\")", '+1', "'@a", 'Ana', '-500', '-500', '1000.50', '']);
    });

    it('todos responden, también sin datos', function () {
        foreach (Informes::INFORMES as $informe) {
            expect(($this->generar)($informe))->toHaveKeys(['columnas', 'filas']);
            expect(($this->generar)($informe)['columnas'])->not->toBeEmpty();
        }
    });

    it('calcula por habitación, canal, cobros y día de la semana', function () {
        $reserva = ($this->reservar)(1, 3, 200000, ['origen_texto' => 'Airbnb', 'comision' => 30000, 'deposito' => 100000]);
        app(GestorReservas::class)->registrarPago($reserva, 50000, MetodoPago::Tarjeta, $this->hoy);

        expect(($this->generar)('unidades')['filas'][0])->toMatchArray([0 => 'Cuarto 101', 1 => '2', 3 => '$2,000.00', 4 => '$1,000.00'])
            ->and(($this->generar)('canales')['filas'][0])->toBe(['Airbnb', '1', '$2,000.00', '$300.00', '$1,700.00'])
            ->and(($this->generar)('cobros')['filas'][0])->toBe([MetodoPago::Tarjeta->etiqueta(), '1', '$500.00'])
            ->and(($this->generar)('dia_semana')['filas'])->toHaveCount(2)
            ->and(($this->generar)('huespedes')['filas'][0])->toBe(['México', '1', '0'])
            ->and(($this->generar)('anticipos')['filas'][0][5])->toBe('$500.00');
    });

    it('cuenta cancelaciones, tareas y gastos', function () {
        $reserva = ($this->reservar)(1, 2, 100000);
        app(GestorReservas::class)->cancelar($reserva);
        ($this->reservar)(3, 4, 100000);
        Tarea::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoTarea::Mantenimiento, 'titulo' => 'x', 'fecha' => $this->hoy, 'estado' => 'hecha']);
        Tarea::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoTarea::Mantenimiento, 'titulo' => 'y', 'fecha' => $this->hoy, 'estado' => 'pendiente']);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => 'gasto', 'metodo' => 'efectivo', 'concepto' => 'Luz', 'importe' => 50000, 'moneda' => 'MXN', 'fecha' => $this->hoy->format('Y-m-d')]);

        expect(($this->generar)('cancelaciones')['filas'][0])->toBe(['Posada', '2', '1', '50.0 %', '$1,000.00', '0'])
            ->and(($this->generar)('limpieza')['filas'][0])->toBe([TipoTarea::Mantenimiento->etiqueta(), '2', '1', '50.0 %'])
            ->and(($this->generar)('gastos')['filas'][0][1])->toBe('Luz');
    });

    it('compara con el año anterior y enseña lo vendido a futuro', function () {
        ($this->reservar)(1, 2, 100000);
        ($this->reservar)(-365, -364, 50000);

        $anterior = Informes::generar('anio_anterior', [$this->propiedad->id], $this->hoy, $this->hoy->addDays(3))['filas'][0];
        expect($anterior[3])->toBe('$1,000.00')->and($anterior[4])->toBe('$500.00')->and($anterior[5])->toBe('100.0 %');
        expect(collect(($this->generar)('futuro')['filas'])->pluck(1)->all())->toContain('1');
    });

    it('la pantalla los enseña y los descarga', function () {
        ($this->reservar)(1, 2, 100000);

        Livewire::test(PaginaInformes::class)->assertOk()->assertSee('Cuarto 101')
            ->set('informe', 'cobros')->assertSee(__('informes.nombres.cobros'))
            ->set('informe', 'unidades')->call('descargarCsv')->assertFileDownloaded();
    });
});
