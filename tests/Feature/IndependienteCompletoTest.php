<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\BaseCargo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\ModoTarifa;
use App\Enums\TipoCupon;
use App\Enums\TipoMovimiento;
use App\Filament\Pages\Calendario;
use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Movimientos\Pages\CreateMovimiento;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\HuespedesRelationManager;
use App\Filament\Resources\Reservas\RelationManagers\NotasRelationManager;
use App\Models\Canal;
use App\Models\ConceptoCargo;
use App\Models\Cupon;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\Cotizador;
use App\Reservas\Cupones;
use App\Reservas\FusionHuespedes;
use App\Reservas\GestorGrupos;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Servicios\Disponibilidad;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'precio_persona_extra' => 20000]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 4, 'orden' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->gestor = app(GestorReservas::class);
    $this->hoy = $this->propiedad->hoy();
    $this->fecha = fn (int $dias): string => $this->hoy->addDays($dias)->format('Y-m-d');
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

it('por noche suma la persona extra; por persona multiplica', function () {
    // 2030-01-07 es lunes: dos noches entre semana.
    $llegada = CarbonImmutable::parse('2030-01-07');
    $salida = $llegada->addDays(2);

    expect(Cotizador::cotizar($this->unidad, $llegada, $salida, 2)->total)->toBe(200000)
        ->and(Cotizador::cotizar($this->unidad, $llegada, $salida, 4)->total)->toBe(280000);

    $this->tarifa->update(['modo' => ModoTarifa::PorPersona, 'precio_noche' => 50000]);
    expect(Cotizador::cotizar($this->unidad->refresh(), $llegada, $salida, 3)->total)->toBe(300000);
});

it('viernes y sábado usan su precio, también dentro de una temporada', function () {
    $this->tarifa->update(['precio_fin_semana' => 150000]);
    // 2030-01-10 es jueves: jueves, viernes, sábado y domingo.
    $llegada = CarbonImmutable::parse('2030-01-10');
    $cotizacion = Cotizador::cotizar($this->unidad->refresh(), $llegada, $llegada->addDays(4), 2);
    expect($cotizacion->noches)->toBe([100000, 150000, 150000, 100000]);

    $this->tarifa->temporadas()->create(['nombre' => 'Alta', 'desde' => '2030-01-01', 'hasta' => '2030-01-31', 'precio_noche' => 200000, 'precio_fin_semana' => 260000, 'estancia_minima' => 3]);
    $cotizacion = Cotizador::cotizar($this->unidad->refresh(), $llegada, $llegada->addDays(2), 2);
    expect($cotizacion->noches)->toBe([200000, 260000])
        ->and($cotizacion->estanciaMinima)->toBe(3)
        ->and($cotizacion->cumpleEstanciaMinima())->toBeFalse();
});

it('el presupuesto desglosa cargos e impuestos incluidos o que se suman', function () {
    Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'IVA', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 16, 'incluido_en_precio' => true]);
    Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Turismo', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 1000, 'incluido_en_precio' => false]);
    $desayuno = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 15000]);
    ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Limpieza', 'base' => BaseCargo::PorEstancia, 'importe' => 30000, 'obligatorio' => true]);
    ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'No elegido', 'base' => BaseCargo::PorEstancia, 'importe' => 99900]);
    $llegada = CarbonImmutable::parse('2030-01-07');

    $p = Cotizador::presupuesto($this->unidad, $llegada, $llegada->addDays(2), 2, [$desayuno->id => 1.0], null, 10000);

    expect($p->alojamiento)->toBe(200000)
        ->and($p->descuento)->toBe(10000)
        ->and($p->totalCargos())->toBe(90000) // desayuno 150 × 2 × 2 + limpieza 300
        ->and($p->impuestosAdicionales())->toBe(4000) // 10 × 2 × 2
        ->and($p->impuestosIncluidos())->toBe(26207) // 190 000 − 190 000 / 1.16
        ->and($p->total())->toBe(284000);
});

it('los cargos se guardan en la reserva: los elegidos y los obligatorios', function () {
    $desayuno = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorEstancia, 'importe' => 15000]);
    ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Limpieza', 'base' => BaseCargo::PorEstancia, 'importe' => 30000, 'obligatorio' => true]);

    $reserva = $this->gestor->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 245000,
        'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(3),
        'cargos' => [['concepto_cargo_id' => $desayuno->id, 'cantidad' => 2]],
    ]);

    expect($reserva->cargos()->orderBy('nombre')->pluck('importe', 'nombre')->all())->toBe(['Desayuno' => 30000, 'Limpieza' => 30000]);

    $this->gestor->modificar($reserva, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 230000,
        'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(3), 'cargos' => [],
    ]);
    expect($reserva->cargos()->pluck('nombre')->all())->toBe(['Limpieza']);
});

it('un cupón se comprueba por fechas, estancia y usos, y queda en la reserva', function () {
    $cupon = Cupon::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'PROMO', 'tipo' => TipoCupon::Porcentaje, 'porcentaje' => 10, 'estancia_minima' => 2, 'usos_maximos' => 1, 'valido_hasta' => $this->hoy->addDays(30)]);
    expect(Cupones::descuento($cupon, 200000))->toBe(20000);

    $datos = fn (int $d, int $h, array $mas = []): array => $mas + ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 1000, 'llegada' => ($this->fecha)($d), 'salida' => ($this->fecha)($h)];

    expect(fn () => $this->gestor->crear($this->propiedad, $datos(1, 2, ['cupon_codigo' => 'PROMO'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_estancia', ['noches' => 2]));
    expect(fn () => $this->gestor->crear($this->propiedad, $datos(40, 42, ['cupon_codigo' => 'PROMO'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_fechas'));
    expect(fn () => $this->gestor->crear($this->propiedad, $datos(1, 3, ['cupon_codigo' => 'NOEXISTE'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_no_existe'));

    $reserva = $this->gestor->crear($this->propiedad, $datos(1, 3, ['cupon_codigo' => 'promo']));
    expect($reserva->cupon_id)->toBe($cupon->id);

    expect(fn () => $this->gestor->crear($this->propiedad, $datos(5, 7, ['cupon_codigo' => 'PROMO'])))->toThrow(ReservaRechazada::class, __('ui.cupones.error_usos'));

    // Al editar la misma reserva no cuenta contra sí misma, y al cancelarla el uso se libera.
    $this->gestor->modificar($reserva, $datos(1, 4, ['cupon_codigo' => 'PROMO', 'reserva_id' => $reserva->id]));
    $this->gestor->cancelar($reserva);
    expect($this->gestor->crear($this->propiedad, $datos(5, 7, ['cupon_codigo' => 'PROMO']))->cupon_id)->toBe($cupon->id);
});

it('mueve una reserva con sus noches y rechaza si ya hay otra', function () {
    $otraUnidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2, 'orden' => 1]);
    $reserva = $this->gestor->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 1000, 'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(4)]);
    $this->gestor->crear($this->propiedad, ['unidad_id' => $otraUnidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 1000, 'llegada' => ($this->fecha)(10), 'salida' => ($this->fecha)(12)]);

    Livewire::test(Calendario::class)->call('moverReserva', $reserva->id, $otraUnidad->id, ($this->fecha)(5))->assertNotified(__('ui.calendario.movida'));

    $reserva->refresh();
    expect($reserva->unidad_id)->toBe($otraUnidad->id)
        ->and($reserva->llegada->format('Y-m-d'))->toBe(($this->fecha)(5))
        ->and($reserva->noches)->toBe(3)
        ->and($reserva->nochesOcupadas()->where('unidad_id', $otraUnidad->id)->count())->toBe(3);

    Livewire::test(Calendario::class)->call('moverReserva', $reserva->id, $otraUnidad->id, ($this->fecha)(9))->assertNotified(__('ui.reservas.error_ocupada'));
    expect($reserva->refresh()->llegada->format('Y-m-d'))->toBe(($this->fecha)(5));
});

it('la primera libre respeta el orden y la capacidad', function () {
    $chica = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '100', 'nombre' => '100', 'capacidad' => 2, 'orden' => 1]);

    expect(Disponibilidad::primeraLibre($this->propiedad->id, ($this->fecha)(1), ($this->fecha)(2), 2)->id)->toBe($chica->id)
        ->and(Disponibilidad::primeraLibre($this->propiedad->id, ($this->fecha)(1), ($this->fecha)(2), 3)->id)->toBe($this->unidad->id)
        ->and(Disponibilidad::primeraLibre($this->propiedad->id, ($this->fecha)(1), ($this->fecha)(2), 9))->toBeNull();
});

it('un grupo crea todas sus reservas o ninguna, y reparte el cobro', function () {
    $segunda = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2]);
    $grupos = app(GestorGrupos::class);
    $llegada = CarbonImmutable::parse(($this->fecha)(1));

    // Una ocupada: no se crea nada.
    $ocupa = $this->gestor->crear($this->propiedad, ['unidad_id' => $segunda->id, 'titular_id' => $this->huesped->id, 'personas' => 1, 'total' => 1, 'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(2)]);
    expect(fn () => $grupos->crear($this->propiedad, 'Boda', $this->huesped->id, [$this->unidad->id, $segunda->id], $llegada, $llegada->addDays(2), 2, EstadoReserva::Confirmada))
        ->toThrow(ReservaRechazada::class);
    expect(Grupo::query()->count())->toBe(0)->and(Reserva::query()->count())->toBe(1);
    $this->gestor->cancelar($ocupa);

    $grupo = $grupos->crear($this->propiedad, 'Boda', $this->huesped->id, [$this->unidad->id, $segunda->id], $llegada, $llegada->addDays(2), 2, EstadoReserva::Confirmada);
    expect($grupo->reservas()->count())->toBe(2);
    $total = $grupos->saldo($grupo)['total'];
    expect($total)->toBeGreaterThan(0);

    $grupos->cobrar($grupo, (int) ($total / 2) + 100, MetodoPago::Efectivo, CarbonImmutable::now());
    $saldo = $grupos->saldo($grupo);
    expect($saldo['pagado'])->toBe((int) ($total / 2) + 100)
        ->and($saldo['pendiente'])->toBe($total - $saldo['pagado']);

    // Lo que sobra queda como saldo a favor en la última.
    $grupos->cobrar($grupo, $saldo['pendiente'] + 500, MetodoPago::Efectivo, CarbonImmutable::now());
    expect($grupos->saldo($grupo)['pendiente'])->toBe(-500);
});

it('crea un grupo desde su formulario', function () {
    Livewire::test(CreateGrupo::class)
        ->fillForm([
            'propiedad_id' => $this->propiedad->id, 'nombre' => 'Boda', 'coordinador_id' => $this->huesped->id, 'estado' => 'confirmada',
            'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(2), 'personas' => 2, 'unidades' => [$this->unidad->id],
        ])
        ->call('create')->assertHasNoFormErrors();

    expect(Grupo::query()->firstOrFail()->reservas()->count())->toBe(1);
    $this->get('/grupos')->assertOk();
    $this->get('/grupos/'.Grupo::query()->firstOrFail()->id)->assertOk()->assertSee('Boda');
});

it('fusiona dos fichas sin perder reservas ni contactos', function () {
    $otra = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana María', 'apellido1' => 'Ejemplo', 'domicilio_ciudad' => 'Colima', 'notas' => 'Alergia']);
    $otra->contactos()->create(['tipo' => 'correo', 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->huesped->contactos()->create(['tipo' => 'correo', 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $otra->contactos()->create(['tipo' => 'telefono', 'valor' => '+52 55', 'valor_normalizado' => '+5255', 'orden' => 1]);
    $reserva = $this->gestor->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $otra->id, 'personas' => 1, 'total' => 1, 'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(2)]);
    $reserva->huespedes()->syncWithoutDetaching([$this->huesped->id => ['orden' => 1]]);

    app(FusionHuespedes::class)->fusionar($this->huesped, $otra);

    expect(Huesped::query()->whereKey($otra->id)->exists())->toBeFalse()
        ->and($reserva->refresh()->titular_id)->toBe($this->huesped->id)
        ->and($reserva->huespedes()->count())->toBe(1)
        ->and($this->huesped->refresh()->nombre)->toBe('Ana')
        ->and($this->huesped->domicilio_ciudad)->toBe('Colima')
        ->and($this->huesped->notas)->toBe('Alergia')
        ->and($this->huesped->contactos()->count())->toBe(2);
});

it('registra gastos con la moneda de la propiedad', function () {
    Livewire::test(CreateMovimiento::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo' => 'gasto', 'fecha' => ($this->fecha)(0), 'concepto' => 'Luz', 'importe' => '500.50', 'metodo' => 'efectivo'])
        ->call('create')->assertHasNoFormErrors();

    $movimiento = Movimiento::query()->firstOrFail();
    expect($movimiento->importe)->toBe(50050)->and($movimiento->moneda)->toBe('MXN')->and($movimiento->tipo)->toBe(TipoMovimiento::Gasto);
});

it('el formulario de reserva calcula el total con persona extra y la comisión del canal', function () {
    Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Booking', 'comision_porcentaje' => 15]);
    $llegada = CarbonImmutable::parse('2030-01-07');

    Livewire::test(CreateReserva::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d')])
        ->fillForm(['personas' => 3])
        ->assertFormSet(['total' => '2400.00'])
        ->fillForm(['origen_texto' => 'Booking'])
        ->assertFormSet(['comision' => '360.00']);
});

it('la ficha de la reserva admite acompañantes y notas', function () {
    $reserva = $this->gestor->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 1, 'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(2)]);

    Livewire::test(HuespedesRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
        ->callTableAction('anadirAcompanante', data: ['nombre' => 'Luis', 'apellido1' => 'Ejemplo', 'parentesco' => 'Hijo'])
        ->assertHasNoTableActionErrors();
    expect($reserva->huespedes()->count())->toBe(2);

    Livewire::test(NotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
        ->callTableAction('anadirNota', data: ['texto' => 'Llega tarde'])
        ->assertHasNoTableActionErrors();
    expect($reserva->notas()->first()->texto)->toBe('Llega tarde');
    $this->get('/reservas/'.$reserva->id)->assertOk();
});

it('las pantallas de catálogo abren', function (string $ruta) {
    $this->get($ruta)->assertOk();
})->with(['/impuestos', '/impuestos/create', '/cargos', '/cargos/create', '/canales', '/canales/create', '/cupones', '/cupones/create', '/cajas', '/cajas/create', '/gastos-e-ingresos', '/gastos-e-ingresos/create', '/grupos', '/grupos/create', '/tarifas/create']);

it('genera la confirmación y el recibo en PDF', function () {
    $reserva = $this->gestor->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 200000, 'llegada' => ($this->fecha)(1), 'salida' => ($this->fecha)(3), 'descuento' => 1000, 'comentario' => 'Cuna']);
    $pago = $this->gestor->registrarPago($reserva, 50000, MetodoPago::Tarjeta, CarbonImmutable::now(), 'R-1');
    $generador = app(App\Documentos\GeneradorPdf::class);

    expect(substr($generador->confirmacion($reserva->refresh()), 0, 5))->toBe('%PDF-')
        ->and(substr($generador->recibo($pago), 0, 5))->toBe('%PDF-');

    Livewire::test(ViewReserva::class, ['record' => $reserva->getKey()])->callAction('confirmacionPdf')->assertFileDownloaded();
});
