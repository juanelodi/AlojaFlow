<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Caja\GestorTurnos;
use App\Caja\TurnoRechazado;
use App\Enums\BaseCargo;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoMovimiento;
use App\Enums\TipoTarea;
use App\Filament\Pages\Informes as PaginaInformes;
use App\Filament\Resources\Bitacora\NotaBitacoraResource;
use App\Filament\Resources\Bitacora\Pages\CreateNotaBitacora;
use App\Filament\Resources\Cajas\CajaResource;
use App\Filament\Resources\Empresas\EmpresaResource;
use App\Filament\Resources\Empresas\Pages\CreateEmpresa;
use App\Filament\Resources\Empresas\Pages\EditEmpresa;
use App\Filament\Resources\Empresas\RelationManagers\ReservasRelationManager;
use App\Filament\Resources\Impuestos\Pages\ListImpuestos;
use App\Filament\Resources\Movimientos\MovimientoResource;
use App\Filament\Resources\Reservas\Pages\CreateReserva;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Filament\Resources\TurnosCaja\Pages\ListTurnosCaja;
use App\Filament\Resources\TurnosCaja\TurnoCajaResource;
use App\Models\Caja;
use App\Models\Canal;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Movimiento;
use App\Models\NotaBitacora;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Tarifa;
use App\Models\TurnoCaja;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Paises\CatalogoIsh;
use App\Paises\CatalogoPaises;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Servicios\CierreDelDia;
use App\Servicios\Grafica;
use App\Servicios\Informes;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'paquete_pais' => PaquetePais::Mexico, 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'U1', 'nombre' => 'Uno', 'capacidad' => 4, 'orden' => 1]);
    $this->titular = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Prueba']);
    $this->caja = Caja::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Recepción', 'activa' => true]);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->recepcion = User::query()->create(['name' => 'Recepción', 'email' => 'recepcion@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (int $llegada, int $salida, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => $this->hoy->addDays($llegada)->format('Y-m-d'), 'salida' => $this->hoy->addDays($salida)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 100000,
    ]);
});

describe('turnos de caja', function (): void {
    it('cuadra el efectivo con el fondo, los cobros y los gastos, y deja el acta', function (): void {
        $this->actingAs($this->recepcion);
        $gestor = app(GestorTurnos::class);
        $turno = $gestor->abrir($this->caja, 50000);
        $reserva = ($this->reservar)(5, 7);

        app(GestorReservas::class)->registrarPago($reserva, 30000, MetodoPago::Efectivo, $this->hoy, null, $this->caja->id);
        app(GestorReservas::class)->registrarPago($reserva, 20000, MetodoPago::Tarjeta, $this->hoy, null, $this->caja->id);
        app(GestorReservas::class)->registrarPago($reserva, 11100, MetodoPago::Efectivo, $this->hoy, null, null); // sin caja: no está en este cajón
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'caja_id' => $this->caja->id, 'fecha' => $this->hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Garrafones', 'metodo' => MetodoPago::Efectivo, 'importe' => 5000, 'moneda' => 'MXN']);

        $resumen = $gestor->resumen($turno->refresh());
        expect($resumen['esperado'])->toBe(75000)->and($resumen['por_metodo'])->toMatchArray(['efectivo' => 30000, 'tarjeta' => 20000]);

        $cerrado = $gestor->cerrar($turno, 74000, 'Faltó cambio');
        expect($cerrado->diferencia)->toBe(-1000)->and($cerrado->esperado)->toBe(75000)->and($cerrado->caja_abierta)->toBeNull()
            ->and($cerrado->resumen['entradas'])->toBe(30000)->and($cerrado->cerrado_por)->toBe($this->recepcion->id);

        expect(fn () => $gestor->cerrar($turno, 1, null))->toThrow(TurnoRechazado::class);
        // Ya cerrado, lo que entre después no cambia el acta.
        expect($gestor->abrir($this->caja, 0)->id)->not->toBe($turno->id);
    });

    it('no deja dos turnos abiertos en una caja', function (): void {
        $this->actingAs($this->recepcion);
        app(GestorTurnos::class)->abrir($this->caja, 0);
        expect(fn () => app(GestorTurnos::class)->abrir($this->caja, 0))->toThrow(TurnoRechazado::class, 'Recepción');
    });

    it('se abre y se cierra desde la pantalla con un usuario sin administrador, sin ver lo esperado antes de contar', function (): void {
        Livewire::actingAs($this->recepcion)->test(ListTurnosCaja::class)
            ->callAction('abrir', ['caja_id' => $this->caja->id, 'fondo' => '500.00'])->assertHasNoActionErrors();
        $turno = TurnoCaja::query()->sole();
        expect($turno->fondo_inicial)->toBe(50000)->and(TurnoCajaResource::canEdit($turno))->toBeFalse();

        Livewire::actingAs($this->recepcion)->test(ListTurnosCaja::class)
            ->assertTableActionHidden('acta', $turno)
            ->callTableAction('cerrar', $turno, ['contado' => '500.00'])->assertHasNoTableActionErrors();
        expect($turno->refresh()->diferencia)->toBe(0)->and($turno->estaAbierto())->toBeFalse();
        Livewire::actingAs($this->recepcion)->test(ListTurnosCaja::class)->assertTableActionVisible('acta', $turno)->assertSee('Recepción');
    });

    it('propone al cobrar la caja del turno de quien cobra, y el cierre del día suma los arqueos', function (): void {
        $this->actingAs($this->recepcion);
        expect(GestorTurnos::cajaPropuesta($this->propiedad->id))->toBeNull();
        $turno = app(GestorTurnos::class)->abrir($this->caja, 1000);
        expect(GestorTurnos::cajaPropuesta($this->propiedad->id))->toBe($this->caja->id);

        app(GestorTurnos::class)->cerrar($turno, 900, null);
        $resumen = app(CierreDelDia::class)->resumen($this->propiedad, $this->hoy);
        expect($resumen['turnos'])->toHaveCount(1)->and($resumen['turnos'][0])->toMatchArray(['caja' => 'Recepción', 'diferencia' => -100]);
    });
});

describe('bitácora', function (): void {
    it('deja notas y lo importante sale en Hoy hasta resolverlo; la de otro no se edita', function (): void {
        Livewire::actingAs($this->recepcion)->test(CreateNotaBitacora::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'texto' => 'La 101 no tiene agua caliente', 'importante' => true])
            ->call('create')->assertHasNoFormErrors();
        $nota = NotaBitacora::query()->sole();
        expect($nota->user_id)->toBe($this->recepcion->id);

        $this->actingAs($this->recepcion)->get('/hoy')->assertOk()->assertSee('La 101 no tiene agua caliente');
        $this->get(NotaBitacoraResource::getUrl())->assertOk()->assertSee('La 101 no tiene agua caliente');

        $otro = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($otro, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        $this->actingAs($otro);
        expect(NotaBitacoraResource::canEdit($nota))->toBeFalse();
        $this->actingAs($this->admin);
        expect(NotaBitacoraResource::canEdit($nota))->toBeTrue();

        $nota->forceFill(['resuelta_en' => now('UTC')])->save();
        $this->actingAs($this->recepcion)->get('/hoy')->assertOk()->assertDontSee('La 101 no tiene agua caliente');
    });

    it('apagada, no abre ni sale en Hoy', function (): void {
        NotaBitacora::query()->create(['propiedad_id' => $this->propiedad->id, 'texto' => 'Nota secreta', 'importante' => true]);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Bitacora)));
        $this->actingAs($this->admin)->get('/hoy')->assertOk()->assertDontSee('Nota secreta');
        expect(NotaBitacoraResource::canViewAny())->toBeFalse();
    });

    it('se borra en la limpieza pasados dos años', function (): void {
        NotaBitacora::query()->create(['propiedad_id' => $this->propiedad->id, 'texto' => 'Vieja']);
        $this->travel(25)->months();
        $this->artisan('alojaflow:limpiar')->assertSuccessful();
        expect(NotaBitacora::query()->count())->toBe(0);
    });
});

describe('empresas y agencias', function (): void {
    it('guarda la empresa de la reserva, solo de su propiedad, y no la borra quien cambia la reserva desde otra pantalla', function (): void {
        $empresa = Empresa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Viajes Sol', 'tipo' => 'agencia', 'comision_porcentaje' => 10, 'dias_credito' => 30]);
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
        $ajena = Empresa::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajena', 'tipo' => 'empresa']);

        $reserva = ($this->reservar)(5, 7, ['empresa_id' => $empresa->id]);
        expect($reserva->empresa_id)->toBe($empresa->id);
        expect(($this->reservar)(10, 12, ['empresa_id' => $ajena->id])->empresa_id)->toBeNull();

        $datos = ['unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => $reserva->llegada->format('Y-m-d'), 'salida' => $reserva->salida->format('Y-m-d'), 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 90000];
        expect(app(GestorReservas::class)->modificar($reserva, $datos)->empresa_id)->toBe($empresa->id);
    });

    it('se administra con un usuario sin administrador y con reservas no se borra', function (): void {
        Livewire::actingAs($this->recepcion)->test(CreateEmpresa::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo' => 'empresa', 'nombre' => 'ACME', 'identificador_fiscal' => 'XAXX010101000', 'dias_credito' => 15, 'activa' => true])
            ->call('create')->assertHasNoFormErrors();
        $empresa = Empresa::query()->sole();
        $this->actingAs($this->recepcion);
        expect(EmpresaResource::canDelete($empresa))->toBeTrue();
        ($this->reservar)(5, 7, ['empresa_id' => $empresa->id]);
        expect(EmpresaResource::canDelete($empresa))->toBeFalse();

        $this->get(EmpresaResource::getUrl('index'))->assertOk()->assertSee('ACME')->assertSee('1,000.00');
        Livewire::actingAs($this->recepcion)->test(EditEmpresa::class, ['record' => $empresa->id])->callAction('estadoCuenta')->assertFileDownloaded(null, null, App\Support\Csv::TIPO_EXCEL);
    });

    it('apagada, no abre', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Empresas)));
        $this->actingAs($this->admin);
        expect(EmpresaResource::canViewAny())->toBeFalse();
    });
});

describe('reglas de impuesto', function (): void {
    beforeEach(function (): void {
        Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Portal', 'cobra_impuestos' => true]);
        $this->impuesto = fn (array $datos): Impuesto => Impuesto::query()->create($datos + ['propiedad_id' => $this->propiedad->id, 'nombre' => 'ISH', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 10, 'incluido_en_precio' => false, 'activo' => true]);
    });

    it('aplica según la vigencia de la fecha de llegada', function (): void {
        ($this->impuesto)(['vigente_desde' => $this->hoy->addDays(10)->format('Y-m-d')]);
        $reserva = ($this->reservar)(5, 7);
        expect($reserva->impuestos()->count())->toBe(0);
        expect(($this->reservar)(20, 22)->impuestos()->count())->toBe(1);
    });

    it('un porcentaje con tope de noches solo cae sobre esa parte', function (): void {
        ($this->impuesto)(['tope_noches' => 2]);
        $reserva = ($this->reservar)(5, 9, ['total' => 105000]); // 4 noches: 100000 de alojamiento + 5 % efectivo
        expect($reserva->impuestos()->sole()->importe)->toBe(5000);
    });

    it('un importe por persona no lo pagan los menores si tiene edad mínima, y un portal no lo cobra dos veces', function (): void {
        ($this->impuesto)(['base' => BaseCargo::PorPersonaNoche, 'tasa' => null, 'importe' => 1000, 'edad_minima' => 18]);
        $tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Base', 'precio_noche' => 50000, 'activa' => true]);
        $this->unidad->update(['tarifa_id' => $tarifa->id]);
        $llegada = $this->hoy->addDays(5);
        $salida = $this->hoy->addDays(7);

        $conMenor = Cotizador::presupuesto($this->unidad, $llegada, $salida, 3, [], null, 0, 1);
        expect($conMenor->impuestosAdicionales())->toBe(4000);

        $delPortal = Cotizador::presupuesto($this->unidad, $llegada, $salida, 2, [], null, 0, 0, true);
        expect($delPortal->impuestosAdicionales())->toBe(0)->and($delPortal->total())->toBe($delPortal->alojamiento);

        $reserva = ($this->reservar)(5, 7, ['origen_texto' => 'Portal']);
        expect($reserva->impuestos()->sole()->cobrado_por_canal)->toBeTrue();
    });

    it('si cambian el total a mano, el impuesto lo sigue, y la foto no cambia con la tasa', function (): void {
        $impuesto = ($this->impuesto)([]);
        $reserva = ($this->reservar)(5, 7, ['total' => 110000]);
        expect($reserva->impuestos()->sole()->importe)->toBe(10000);

        $impuesto->update(['tasa' => 50]);
        expect($reserva->impuestos()->sole()->importe)->toBe(10000);

        app(GestorReservas::class)->mover($reserva, $this->unidad->id, $this->hoy->addDays(30));
        expect($reserva->impuestos()->sole()->importe)->toBe(36667);
    });

    it('propone el ISH de México por estado, con su fuente, y no a otros países', function (): void {
        expect(CatalogoIsh::todas())->toHaveCount(32);
        Livewire::actingAs($this->admin)->test(ListImpuestos::class)
            ->assertActionVisible('catalogo')
            ->callAction('catalogo', ['region' => 'Quintana Roo', 'tasa' => 5])->assertHasNoActionErrors();
        $impuesto = Impuesto::query()->sole();
        expect($impuesto->codigo)->toBe('ISH')->and($impuesto->nombre)->toContain('Quintana Roo')->and((float) $impuesto->tasa)->toBe(5.0)->and($impuesto->incluido_en_precio)->toBeFalse();

        $this->propiedad->update(['pais' => 'US', 'paquete_pais' => PaquetePais::EstadosUnidos]);
        Livewire::actingAs($this->admin)->test(ListImpuestos::class)->assertActionHidden('catalogo');
    });
});

describe('informes', function (): void {
    it('todos abren con usuarios sin administrador y con datos', function (): void {
        $contador = User::query()->create(['name' => 'Contador', 'email' => 'contador@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
        $reserva = ($this->reservar)(0, 2);
        app(GestorReservas::class)->registrarPago($reserva, 1000, MetodoPago::Efectivo, $this->hoy, null, null);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'fecha' => $this->hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Luz', 'metodo' => MetodoPago::Efectivo, 'importe' => 500, 'moneda' => 'MXN']);

        foreach (Informes::INFORMES as $informe) {
            $pagina = Livewire::actingAs($contador)->test(PaginaInformes::class)->set('informe', $informe)
                ->set('desde', $this->hoy->startOfMonth()->format('Y-m-d'))->set('hasta', $this->hoy->addMonths(2)->endOfMonth()->format('Y-m-d'));
            $pagina->assertOk()->assertSee(__('informes.nombres.'.$informe));
            expect($pagina->instance()->datos()['columnas'])->not->toBeEmpty();
        }
        Livewire::actingAs($contador)->test(PaginaInformes::class)->call('descargarPdf')->assertFileDownloaded();
        Livewire::actingAs($contador)->test(PaginaInformes::class)->call('descargarCsv')->assertFileDownloaded();
    });

    it('el resumen saca ocupación, ADR y RevPAR con las fórmulas de siempre', function (): void {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'U2', 'nombre' => 'Dos', 'capacidad' => 4, 'orden' => 2]);
        $inicio = CarbonImmutable::parse('2030-03-01');
        $reserva = app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => '2030-03-10', 'salida' => '2030-03-14', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 400000]);

        $datos = Informes::generar('resumen_anio', [$this->propiedad->id], $inicio, $inicio->endOfMonth()->startOfDay());
        // 2 unidades × 31 días = 62 noches disponibles; 4 vendidas.
        expect($datos['filas'][0][1])->toBe('4')->and($datos['filas'][0][2])->toContain('6.5')
            ->and($datos['filas'][0][4])->toContain('1,000.00')->and($datos['filas'][0][5])->toContain('64.52')
            ->and($reserva->id)->toBeGreaterThan(0);
    });

    it('el pick-up cuenta lo reservado en el periodo, sea cuando sea la estancia', function (): void {
        ($this->reservar)(40, 42);
        $vieja = ($this->reservar)(50, 53);
        DB::table('reservas')->where('id', $vieja->id)->update(['creado_en' => $this->hoy->subMonths(3)->format('Y-m-d H:i:s')]);

        $datos = Informes::generar('pickup', [$this->propiedad->id], $this->hoy->subDays(2), $this->hoy->addDay());
        expect(array_sum(array_map(fn (array $f): int => (int) $f[1], $datos['filas'])))->toBe(2);
    });

    it('el pronóstico suma lo que el año pasado todavía entró desde esta fecha', function (): void {
        $mes = $this->hoy->startOfMonth()->addMonths(2);
        $hoy = CarbonImmutable::now();
        $crear = fn (CarbonImmutable $llegada, int $noches): Reserva => app(GestorReservas::class)->crear($this->propiedad, ['unidad_id' => $this->unidad->id, 'titular_id' => $this->titular->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays($noches)->format('Y-m-d'), 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000]);
        $crear($mes->addDays(2), 3);
        // Del año pasado: 2 noches ya estaban vendidas a esta fecha y 5 se vendieron después.
        $temprana = $crear($mes->subYear()->addDays(2), 2);
        $tardia = $crear($mes->subYear()->addDays(8), 5);
        DB::table('reservas')->where('id', $temprana->id)->update(['creado_en' => $hoy->subYear()->subDays(5)->format('Y-m-d H:i:s')]);
        DB::table('reservas')->where('id', $tardia->id)->update(['creado_en' => $hoy->subYear()->addDays(5)->format('Y-m-d H:i:s')]);

        $datos = Informes::generar('pronostico', [$this->propiedad->id], $this->hoy, $this->hoy);
        // vendidas, total del año pasado, a esta fecha, pronóstico.
        expect(array_slice($datos['filas'][2], 1, 4))->toBe(['3', '7', '2', '8']);
    });

    it('cuentas por cobrar vence con el crédito de la empresa y no enseña nombres a quien no ve datos', function (): void {
        $empresa = Empresa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'ACME', 'tipo' => 'empresa', 'dias_credito' => 30]);
        ($this->reservar)(-20, -15, ['empresa_id' => $empresa->id]);
        ($this->reservar)(-50, -45);
        $this->actingAs($this->admin);

        $datos = Informes::generar('por_cobrar', [$this->propiedad->id], $this->hoy, $this->hoy);
        expect($datos['filas'])->toHaveCount(2)
            ->and($datos['filas'][0][1])->toContain('Ana')->and((int) $datos['filas'][0][3])->toBeGreaterThan(40)
            ->and($datos['filas'][1][1])->toBe('ACME')->and($datos['filas'][1][3])->toBe('0')
            ->and(collect($datos['grafica']['filas'])->pluck('etiqueta')->all())->toBe([__('informes.tramos.sin_vencer'), __('informes.tramos.hasta_60')]);

        $soloIndicadores = User::query()->create(['name' => 'Sin datos', 'email' => 'sindatos@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($soloIndicadores, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza', 'agregados' => [Acceso::INDICADORES]]]);
        $this->actingAs($soloIndicadores);
        expect(Informes::generar('por_cobrar', [$this->propiedad->id], $this->hoy, $this->hoy)['filas'][0][1])->toBe('');
    });

    it('ingresos contra gastos mira el dinero real, por moneda', function (): void {
        $reserva = ($this->reservar)(1, 2);
        app(GestorReservas::class)->registrarPago($reserva, 3000, MetodoPago::Efectivo, $this->hoy, null, null);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'fecha' => $this->hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Ingreso, 'concepto' => 'Renta', 'metodo' => MetodoPago::Efectivo, 'importe' => 2000, 'moneda' => 'MXN']);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'fecha' => $this->hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Luz', 'metodo' => MetodoPago::Efectivo, 'importe' => 1000, 'moneda' => 'MXN']);

        $fila = Informes::generar('ingresos_gastos', [$this->propiedad->id], $this->hoy->startOfMonth(), $this->hoy->endOfMonth()->startOfDay())['filas'][0];
        expect($fila[1])->toContain('30.00')->and($fila[2])->toContain('20.00')->and($fila[3])->toContain('10.00')->and($fila[4])->toContain('40.00');
    });

    it('impuestos cobrados separa lo que cobró el portal', function (): void {
        Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Portal', 'cobra_impuestos' => true]);
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'ISH', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 10, 'incluido_en_precio' => false, 'activo' => true]);
        ($this->reservar)(1, 2, ['total' => 110000]);
        ($this->reservar)(3, 4, ['total' => 100000, 'origen_texto' => 'Portal']);

        $fila = Informes::generar('impuestos', [$this->propiedad->id], $this->hoy, $this->hoy->addDays(5))['filas'][0];
        expect($fila[0])->toBe('ISH')->and($fila[1])->toBe('2')->and($fila[4])->toContain('100.00')->and($fila[5])->toContain('100.00');
    });

    it('limpieza por persona y grupos', function (): void {
        Tarea::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => TipoTarea::LimpiezaSalida, 'titulo' => 'x', 'fecha' => $this->hoy->format('Y-m-d'), 'estado' => EstadoTarea::Hecha, 'hecha_por' => $this->recepcion->id]);
        $fila = Informes::generar('limpieza_persona', [$this->propiedad->id], $this->hoy, $this->hoy)['filas'][0];
        expect($fila[0])->toBe('Recepción')->and($fila[2])->toBe('1');

        $grupo = Grupo::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'G1', 'nombre' => 'Boda']);
        ($this->reservar)(1, 3, ['grupo_id' => $grupo->id]);
        $fila = Informes::generar('grupos', [$this->propiedad->id], $this->hoy, $this->hoy->addDays(3))['filas'][0];
        expect($fila[0])->toBe('Boda')->and($fila[1])->toBe('1')->and($fila[2])->toBe('2');
    });

    it('sin gráfica si hay dos monedas o no hay nada que dibujar', function (): void {
        $filas = [['a', 1, 'MXN'], ['b', 2, 'USD']];
        expect(Grafica::de(['x'], $filas, fn (array $f): string => $f[0], fn (array $f): array => [[$f[1], (string) $f[1]]], fn (array $f): string => $f[2]))->toBeNull();
        expect(Grafica::de(['x'], [['a', 0, 'MXN']], fn (array $f): string => $f[0], fn (array $f): array => [[$f[1], '0']]))->toBeNull();
        expect(Grafica::de(['x'], [['a', 5, 'MXN']], fn (array $f): string => $f[0], fn (array $f): array => [[$f[1], '5']])['maximo'])->toBe(5.0);
    });
});

it('una caja, un pago o un movimiento de la principal se ven, pero nadie los cambia', function (): void {
    $sub = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $mov = Movimiento::query()->create(['propiedad_id' => $sub->id, 'fecha' => $this->hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Luz', 'metodo' => MetodoPago::Efectivo, 'importe' => 500, 'moneda' => 'MXN', 'sincronizada' => true]);
    $caja = Caja::query()->create(['propiedad_id' => $sub->id, 'nombre' => 'De la principal', 'activa' => true, 'sincronizada' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion'], ['propiedad_id' => $sub->id, 'rol' => 'contador']]);

    $this->actingAs($this->recepcion);
    expect(MovimientoResource::getEloquentQuery()->pluck('id')->all())->toContain($mov->id)
        ->and(MovimientoResource::canEdit($mov))->toBeFalse()
        ->and(CajaResource::getEloquentQuery()->pluck('id')->all())->toContain($caja->id)
        ->and(CajaResource::canEdit($caja))->toBeFalse()
        ->and(MovimientoResource::canCreate())->toBeTrue();

    expect(fn () => $mov->update(['concepto' => 'Otro']))->toThrow(LogicException::class);
    expect(fn () => $caja->delete())->toThrow(LogicException::class);
    $this->get(MovimientoResource::getUrl('index'))->assertOk()->assertSee('Luz');

    $soloSub = User::query()->create(['name' => 'Solo sub', 'email' => 'solosub@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($soloSub, [['propiedad_id' => $sub->id, 'rol' => 'contador']]);
    $this->actingAs($soloSub);
    expect(MovimientoResource::canViewAny())->toBeTrue()->and(MovimientoResource::canCreate())->toBeFalse();
    expect(Pago::query()->count())->toBe(0);
});

it('el formulario de reserva lleva la empresa con su comisión y, si hace falta, los menores', function (): void {
    $tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Base', 'precio_noche' => 100000, 'activa' => true]);
    $this->unidad->update(['tarifa_id' => $tarifa->id]);
    $empresa = Empresa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Viajes Sol', 'tipo' => 'agencia', 'comision_porcentaje' => 10, 'activa' => true]);
    $llegada = $this->hoy->addDays(10);

    $pagina = Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d'), 'personas' => 2, 'titular_id' => $this->titular->id])
        ->assertFormFieldIsHidden('ninos')
        ->fillForm(['empresa_id' => $empresa->id])
        ->assertFormSet(['total' => '2000.00', 'comision' => '200.00']);
    $pagina->call('create')->assertHasNoFormErrors();
    $reserva = Reserva::query()->sole();
    expect($reserva->empresa_id)->toBe($empresa->id);

    Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Tasa', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 100, 'edad_minima' => 16, 'incluido_en_precio' => false, 'activo' => true]);
    Livewire::actingAs($this->recepcion)->test(EditReserva::class, ['record' => $reserva->id])->assertFormFieldIsVisible('ninos')->assertSee('Viajes Sol');

    // Una agencia desactivada no se pierde al cambiar otra cosa de la reserva.
    $empresa->update(['activa' => false]);
    Livewire::actingAs($this->recepcion)->test(EditReserva::class, ['record' => $reserva->id])->fillForm(['comentario' => 'Llegan tarde'])->call('save')->assertHasNoFormErrors();
    expect($reserva->refresh()->empresa_id)->toBe($empresa->id)->and($reserva->comentario)->toBe('Llegan tarde');

    Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Empresas)));
    Livewire::actingAs($this->recepcion)->test(CreateReserva::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id])->assertFormFieldIsHidden('empresa_id');
});

it('el informe de huéspedes junta el país aunque esté escrito de varias formas', function (): void {
    foreach (['MX', 'Mx', 'México', 'Messico', 'España', 'Marte'] as $i => $pais) {
        $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'H'.$i, 'nacionalidad_texto' => $pais]);
        ($this->reservar)(2 + 3 * $i, 3 + 3 * $i, ['titular_id' => $huesped->id]);
    }
    $filas = Informes::generar('huespedes', [$this->propiedad->id], $this->hoy, $this->hoy->addDays(30))['filas'];

    expect($filas[0][1])->toBe('4')->and($filas[0][0])->toContain('xico')
        ->and(collect($filas)->pluck(0)->all())->toContain('Marte')
        ->and(CatalogoPaises::codigoDe('E.E. U.U.'))->toBeNull()
        ->and(CatalogoPaises::codigoDe('EEUU'))->toBe('US')
        ->and(CatalogoPaises::codigoDe(' méxico '))->toBe('MX');
});

it('quien solo ve lo suyo tampoco ve lo ajeno en la empresa, y la auditoría no copia sus datos', function (): void {
    $empresa = Empresa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'ACME', 'tipo' => 'empresa', 'contacto' => 'Laura Secreta']);
    $ajena = ($this->reservar)(5, 7, ['empresa_id' => $empresa->id]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);

    Livewire::actingAs($this->recepcion)->test(ReservasRelationManager::class, ['ownerRecord' => $empresa, 'pageClass' => EditEmpresa::class])
        ->assertCanNotSeeTableRecords([$ajena]);

    $this->actingAs($this->admin);
    $empresa->update(['contacto' => 'Otra Secreta']);
    expect((string) json_encode(RegistroAuditoria::query()->pluck('cambios')->all()))->not->toContain('Secreta')->toContain('contacto');
});
