<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Caja\GestorTurnos;
use App\Dinero\PagoRecibido;
use App\Dinero\SegundaMoneda;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\PagosRelationManager;
use App\Models\Caja;
use App\Models\Huesped;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Publico\MiReserva;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Formato;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Playa', 'codigo' => 'playa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '1', 'nombre' => 'Uno', 'capacidad' => 2]);
    $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->hoy = $this->propiedad->hoy();
    $this->reserva = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->addDays(2)->format('Y-m-d'),
        'salida' => $this->hoy->addDays(4)->format('Y-m-d'), 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 370000]);
    $this->gerente = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
});

it('convierte con el tipo escrito a mano y se guarda desde la propiedad', function (): void {
    // Las propiedades las edita el administrador.
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
    Livewire::test(EditPropiedad::class, ['record' => $this->propiedad->getRouteKey()])
        ->fillForm(['segunda_moneda' => 'usd', 'tipo_cambio' => '18.5'])->call('save')->assertHasNoFormErrors();
    $segunda = new SegundaMoneda($this->propiedad->refresh());

    expect($segunda->moneda())->toBe('USD')->and($segunda->tipo())->toBe('18.5')
        ->and($segunda->equivalente(370000))->toBe(20000)
        ->and($segunda->aPrincipal(10000, '18.5'))->toBe(185000)
        ->and($segunda->texto(370000))->toBe('≈ '.Formato::dinero(20000, 'USD'));

    // La misma que la de la propiedad no vale; vacía, se quita.
    Livewire::test(EditPropiedad::class, ['record' => $this->propiedad->getRouteKey()])
        ->fillForm(['segunda_moneda' => 'MXN', 'tipo_cambio' => '1'])->call('save')->assertHasFormErrors(['segunda_moneda']);
    Livewire::test(EditPropiedad::class, ['record' => $this->propiedad->getRouteKey()])->assertFormSet(['segunda_moneda' => 'USD', 'tipo_cambio' => '18.5'])
        ->fillForm(['segunda_moneda' => null, 'tipo_cambio' => null])->call('save')->assertHasNoFormErrors();
    expect((new SegundaMoneda($this->propiedad->refresh()))->activa())->toBeFalse()
        ->and($this->propiedad->ajustes)->not->toHaveKey(SegundaMoneda::AJUSTE);
});

it('un pago en dólares se anota en pesos con lo recibido, y el recibo lo dice', function (): void {
    (new SegundaMoneda($this->propiedad))->guardar('USD', '18.5');

    Livewire::test(PagosRelationManager::class, ['ownerRecord' => $this->reserva, 'pageClass' => ViewReserva::class])
        ->mountTableAction('registrarPago')
        ->setTableActionData(['recibido_en' => 'USD'])
        ->assertTableActionDataSet(['importe' => '200.00', 'tipo_cambio' => '18.5'])
        ->setTableActionData(['importe' => '100', 'tipo_cambio' => '18', 'fecha' => $this->hoy->format('Y-m-d'), 'metodo' => MetodoPago::Efectivo->value])
        ->callMountedTableAction()->assertHasNoTableActionErrors();

    $pago = Pago::query()->sole();
    expect($pago->importe)->toBe(180000)->and($pago->moneda)->toBe('MXN')
        ->and($pago->moneda_recibida)->toBe('USD')->and($pago->importe_recibido)->toBe(10000)->and((float) $pago->tipo_cambio)->toBe(18.0)
        ->and($this->reserva->refresh()->pagado)->toBe(180000);

    $html = view('documentos.recibo', ['pago' => $pago, 'reserva' => $this->reserva, 'propiedad' => $this->propiedad])->render();
    expect($html)->toContain(Formato::dinero(10000, 'USD'));
});

it('el efectivo en dólares se cuenta aparte en la caja', function (): void {
    $caja = Caja::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Recepción', 'activa' => true]);
    $gestor = app(GestorTurnos::class);
    $turno = $gestor->abrir($caja, 50000);
    app(GestorReservas::class)->registrarPago($this->reserva, 30000, MetodoPago::Efectivo, $this->hoy, null, $caja->id);
    app(GestorReservas::class)->registrarPago($this->reserva, 185000, MetodoPago::Efectivo, $this->hoy, null, $caja->id, new PagoRecibido('USD', 10000, '18.5'));

    $resumen = $gestor->resumen($turno->refresh());
    expect($resumen['esperado'])->toBe(80000)
        ->and($resumen['otras_monedas'])->toBe(['USD' => 10000])
        ->and($resumen['por_metodo']['efectivo'])->toBe(215000);
});

it('el huésped ve lo que sería en la segunda moneda', function (): void {
    (new SegundaMoneda($this->propiedad))->guardar('USD', '18.5');

    $this->get(app(MiReserva::class)->urlDe($this->reserva, 'es'))->assertOk()->assertSee('≈ '.Formato::dinero(20000, 'USD'));
});
