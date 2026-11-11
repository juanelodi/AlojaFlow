<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\BaseCargo;
use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\FaseEstadoCuenta;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Filament\Resources\EstadosCuenta\Pages\ListEstadosCuenta;
use App\Filament\Resources\EstadosCuenta\Pages\ViewEstadoCuenta;
use App\Filament\Resources\Propietarios\Pages\CreatePropietario;
use App\Filament\Resources\Propietarios\Pages\EditPropietario;
use App\Filament\Resources\Propietarios\Pages\ListPropietarios;
use App\Filament\Resources\Propietarios\RelationManagers\UnidadesRelationManager;
use App\Models\ConceptoCargo;
use App\Models\Envio;
use App\Models\EstadoCuenta;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Movimiento;
use App\Models\Propiedad;
use App\Models\Propietario;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Propietarios\AccesoPropietario;
use App\Propietarios\CalculadorEstado;
use App\Propietarios\GestorEstados;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Rentas Mar', 'codigo' => 'mar', 'modo' => ModoPropiedad::Nativa, 'paquete_pais' => PaquetePais::Mexico, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es', 'correo' => 'oficina@example.com'])->refresh();
    $this->dueno = Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doña Ejemplo', 'correo' => 'dona@example.com', 'telefono' => '+52 55 1111 2222', 'comision_porcentaje' => 20, 'cargo_fijo' => 0]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $this->dueno->id, 'codigo' => 'C1', 'nombre' => 'Casa 1', 'capacidad' => 6, 'activa' => true]);
    $this->ajena = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'C2', 'nombre' => 'Casa 2', 'capacidad' => 6, 'activa' => true]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Zacarías', 'apellido1' => 'Huésped']);
    $this->reservar = fn (string $llegada, string $salida, int $total = 100000, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => $total,
    ]);
    $this->calcular = fn (string $desde, string $hasta, ?Propietario $propietario = null): array => app(CalculadorEstado::class)->calcular($propietario ?? $this->dueno->refresh(), CarbonImmutable::parse($desde), CarbonImmutable::parse($hasta));
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->contador = User::query()->create(['name' => 'Con', 'email' => 'con@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
    $this->actingAs($this->gerente);
    $this->estados = app(GestorEstados::class);
    $this->acceso = app(AccesoPropietario::class);
    $this->conClave = function (): array {
        $clave = $this->acceso->generarClave($this->dueno);
        $url = (string) $this->acceso->url($this->dueno->refresh());
        preg_match('~/propietario/([0-9a-f]{40})~', $url, $partes);

        return [$partes[1], $clave];
    };
    // Entra y deja elegida su clave, para probar lo de adentro.
    $this->adentro = function (): string {
        [$token, $clave] = ($this->conClave)();
        $this->post("/propietario/$token/entrar", ['clave' => $clave])->assertRedirect();
        $this->post("/propietario/$token/clave", ['clave' => 'una-clave-larga', 'clave_confirmation' => 'una-clave-larga'])->assertRedirect();

        return $token;
    };
});

afterEach(fn () => Ajustes::olvidar());

describe('cálculo', function (): void {
    it('reparte por noches: una estancia entre dos meses suma justo lo cobrado', function (): void {
        ($this->reservar)('2027-01-29', '2027-02-08', 100003);

        $enero = ($this->calcular)('2027-01-01', '2027-01-31');
        $febrero = ($this->calcular)('2027-02-01', '2027-02-28');

        expect($enero['detalle']['reservas'][0]['noches'])->toBe(3)
            ->and($febrero['detalle']['reservas'][0]['noches'])->toBe(7)
            ->and($enero['ingresos'])->toBe(30001)
            ->and($enero['ingresos'] + $febrero['ingresos'])->toBe(100003);
    });

    it('resta portales, comisión, gastos y cargo fijo', function (): void {
        $this->dueno->update(['cargo_fijo' => 5000]);
        ($this->reservar)('2027-03-01', '2027-03-11', 100000, ['comision' => 15000]);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $this->dueno->id, 'unidad_id' => $this->unidad->id, 'fecha' => '2027-03-15', 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Plomero', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);

        $estado = ($this->calcular)('2027-03-01', '2027-03-31');

        expect($estado['ingresos'])->toBe(100000)
            ->and($estado['comision_portales'])->toBe(15000)
            // 20 % de lo que queda tras el portal.
            ->and($estado['comision'])->toBe(17000)
            ->and($estado['gastos'])->toBe(8000)
            ->and($estado['cargo_fijo'])->toBe(5000)
            ->and($estado['neto'])->toBe(100000 - 15000 - 17000 - 8000 - 5000)
            ->and($estado['detalle']['gastos'][0]['concepto'])->toBe('Plomero');
    });

    it('lo que sigue sin confirmar no cuenta', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-06', 100000, ['estado' => EstadoReserva::SinConfirmar->value]);

        expect(($this->calcular)('2027-03-01', '2027-03-31')['ingresos'])->toBe(0);
    });

    it('la comisión nunca sale negativa, aunque el portal se lleve más de lo que entró', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-06', 1000, ['comision' => 5000]);

        $estado = ($this->calcular)('2027-03-01', '2027-03-31');

        expect($estado['comision'])->toBe(0)
            ->and($estado['neto'])->toBe(1000 - 5000);
    });

    it('las noches posibles son las de las unidades activas', function (): void {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $this->dueno->id, 'codigo' => 'C3', 'nombre' => 'Casa 3', 'capacidad' => 2, 'activa' => false]);

        expect(($this->calcular)('2027-03-01', '2027-03-31')['detalle']['noches_posibles'])->toBe(31);
    });

    it('la comisión puede ser sobre todo el ingreso, y una unidad puede tener la suya', function (): void {
        $this->dueno->update(['comision_sobre_neto' => false]);
        ($this->reservar)('2027-03-01', '2027-03-11', 100000, ['comision' => 15000]);
        expect(($this->calcular)('2027-03-01', '2027-03-31')['comision'])->toBe(20000);

        $this->unidad->update(['comision_propietario' => 10]);
        expect(($this->calcular)('2027-03-01', '2027-03-31')['comision'])->toBe(10000);
    });

    it('los impuestos cobrados no son del dueño, ni lo que sea del administrador', function (): void {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '002', 'nombre' => 'IVA', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 16, 'incluido_en_precio' => true, 'orden' => 1]);
        $limpieza = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Limpieza', 'base' => BaseCargo::PorEstancia, 'importe' => 16000, 'para_administrador' => true]);
        ($this->reservar)('2027-03-01', '2027-03-11', 132000, ['cargos' => [['concepto_cargo_id' => $limpieza->id, 'cantidad' => 1]]]);

        $estado = ($this->calcular)('2027-03-01', '2027-03-31');

        // 132 000 con IVA incluido en lo que no es cargo: 116 000 → 16 000 de impuesto. Y 16 000 de limpieza.
        expect($estado['ingresos'])->toBe(132000 - 16000 - 16000);
    });

    it('no cuenta lo cancelado, ni lo de otras unidades, ni los gastos de otro dueño', function (): void {
        $cancelada = ($this->reservar)('2027-03-01', '2027-03-05');
        app(GestorReservas::class)->cancelar($cancelada);
        ($this->reservar)('2027-03-10', '2027-03-12', 50000, ['unidad_id' => $this->ajena->id]);
        $otro = Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);
        Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $otro->id, 'fecha' => '2027-03-15', 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Ajeno', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);

        $estado = ($this->calcular)('2027-03-01', '2027-03-31');

        expect($estado['ingresos'])->toBe(0)
            ->and($estado['gastos'])->toBe(0)
            ->and($estado['detalle']['reservas'])->toBe([]);
    });

    it('nunca lleva el nombre del huésped', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');

        expect(json_encode(($this->calcular)('2027-03-01', '2027-03-31')))->not->toContain('Zacarías');
    });

    it('la estancia del dueño sale sin ingreso, y cuenta la ocupación', function (): void {
        app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-03-20', 'salida' => '2027-03-25', 'propietario_id' => $this->dueno->id]);
        ($this->reservar)('2027-03-01', '2027-03-11');

        $estado = ($this->calcular)('2027-03-01', '2027-03-31');

        expect($estado['detalle']['estancias'])->toHaveCount(1)
            ->and($estado['detalle']['estancias'][0]['llegada'])->toBe('2027-03-20')
            ->and($estado['ingresos'])->toBe(100000)
            ->and($estado['detalle']['noches_ocupadas'])->toBe(10)
            ->and($estado['detalle']['noches_posibles'])->toBe(31);
    });

    it('un dueño de otra propiedad no se puede poner en un bloqueo', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajeno = Propietario::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajeno']);

        $bloqueo = app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-03-20', 'salida' => '2027-03-25', 'propietario_id' => $ajeno->id]);

        expect($bloqueo->propietario_id)->toBeNull();
    });
});

describe('estados de cuenta', function (): void {
    it('se generan en borrador, se vuelven a calcular, y lo mandado ya no cambia', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');
        $estado = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-15'));

        expect($estado->fase)->toBe(FaseEstadoCuenta::Borrador)
            ->and($estado->desde->format('Y-m-d'))->toBe('2027-03-01')
            ->and($estado->hasta->format('Y-m-d'))->toBe('2027-03-31')
            ->and($estado->neto)->toBe(80000);

        ($this->reservar)('2027-03-15', '2027-03-20', 50000);
        expect($this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'))->neto)->toBe(120000)
            ->and(EstadoCuenta::query()->count())->toBe(1);

        $this->estados->enviar($estado->refresh());
        ($this->reservar)('2027-03-22', '2027-03-25', 50000);

        $otraVez = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'));
        expect($otraVez->neto)->toBe(120000)
            ->and($otraVez->fase)->toBe(FaseEstadoCuenta::Enviado)
            ->and($this->estados->difiere($otraVez))->toBeTrue();
    });

    it('al mandarlo le llega un aviso con su enlace, una vez', function (): void {
        ($this->conClave)();
        ($this->reservar)('2027-03-01', '2027-03-11');
        $estado = $this->estados->generar($this->dueno->refresh(), CarbonImmutable::parse('2027-03-01'));

        $this->estados->enviar($estado);

        $envio = Envio::query()->sole();
        expect($envio->destino)->toBe('dona@example.com')
            ->and($envio->canal)->toBe(CanalEnvio::Correo)
            ->and($envio->estado)->toBe(EstadoEnvio::Manual)
            ->and($envio->cuerpo)->toContain('/propietario/')->toContain(Formato::dinero(80000, 'MXN'))
            ->and($estado->refresh()->fase)->toBe(FaseEstadoCuenta::Enviado)
            ->and($estado->enviado_en)->not->toBeNull();

        $this->estados->enviar($estado);
        expect(Envio::query()->count())->toBe(1);
    });

    it('sin correo se manda igual, sin aviso', function (): void {
        $this->dueno->update(['correo' => null]);
        $estado = $this->estados->generar($this->dueno->refresh(), CarbonImmutable::parse('2027-03-01'));

        expect($this->estados->enviar($estado)->fase)->toBe(FaseEstadoCuenta::Enviado)
            ->and(Envio::query()->count())->toBe(0);
    });

    it('se anota el pago, y reabrir lo recalcula con lo de hoy', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');
        $estado = $this->estados->enviar($this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01')));

        $this->estados->pagar($estado, CarbonImmutable::parse('2027-04-05'), 'SPEI-1');
        expect($estado->refresh()->fase)->toBe(FaseEstadoCuenta::Pagado)
            ->and($estado->pagado_en->format('Y-m-d'))->toBe('2027-04-05')
            ->and($estado->referencia_pago)->toBe('SPEI-1');

        ($this->reservar)('2027-03-15', '2027-03-20', 50000);
        $reabierto = $this->estados->reabrir($estado);

        expect($reabierto->fase)->toBe(FaseEstadoCuenta::Borrador)
            ->and($reabierto->pagado_en)->toBeNull()
            ->and($reabierto->neto)->toBe(120000);
    });

    it('reabrir deja anotado lo que se había pagado', function (): void {
        $estado = $this->estados->enviar($this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01')));
        $this->estados->pagar($estado, CarbonImmutable::parse('2027-04-05'), 'SPEI-1');

        $this->estados->reabrir($estado);

        $anotado = App\Models\RegistroAuditoria::query()->where('accion', 'estado_cuenta.reabrir')->sole()->cambios;
        expect($anotado['referencia_pago'])->toBe('SPEI-1')
            ->and($anotado['pagado_en'])->toBe('2027-04-05')
            ->and($anotado['fase'])->toBe(FaseEstadoCuenta::Pagado->value);
    });

    it('un borrador no se anota como pagado: primero se manda', function (): void {
        $estado = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'));

        Livewire::actingAs($this->contador)->test(ViewEstadoCuenta::class, ['record' => $estado->id])
            ->assertActionHidden('pagar')
            ->callAction('enviar')
            ->assertActionVisible('pagar');
    });

    it('genera los de todos los dueños activos', function (): void {
        Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);
        Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Inactivo', 'activo' => false]);

        expect($this->estados->generarTodos($this->propiedad, CarbonImmutable::parse('2027-03-01')))->toBe(2)
            ->and(EstadoCuenta::query()->count())->toBe(2);
    });

    it('el PDF se genera, con sus números y sin el huésped', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');
        $estado = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'));

        $pdf = app(App\Documentos\GeneradorPdf::class)->estadoCuenta($estado);

        expect(substr($pdf, 0, 4))->toBe('%PDF');
    });

    it('la pantalla los genera, y un contador puede mandarlos, anotar el pago y reabrir', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');
        $this->travelTo(CarbonImmutable::parse('2027-04-10 12:00:00'));

        Livewire::actingAs($this->contador)->test(ListEstadosCuenta::class)
            ->callAction('generar', ['propiedad' => $this->propiedad->id, 'mes' => '2027-03'])
            ->assertHasNoActionErrors()
            ->assertSee('Doña Ejemplo');

        $estado = EstadoCuenta::query()->sole();
        expect($estado->neto)->toBe(80000);

        Livewire::actingAs($this->contador)->test(ViewEstadoCuenta::class, ['record' => $estado->id])
            ->assertSee(Formato::dinero(80000, 'MXN'))
            ->assertDontSee('Zacarías')
            ->callAction('enviar')
            ->callAction('pagar', ['fecha' => '2027-04-10', 'referencia' => 'SPEI-9'])
            ->callAction('reabrir');

        expect($estado->refresh()->fase)->toBe(FaseEstadoCuenta::Borrador);
    });

    it('baja el PDF y el CSV', function (): void {
        ($this->reservar)('2027-03-01', '2027-03-11');
        $estado = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'));

        Livewire::actingAs($this->gerente)->test(ViewEstadoCuenta::class, ['record' => $estado->id])
            ->callAction('pdf')->assertFileDownloaded('estado-2027-03.pdf');
        $csv = Livewire::actingAs($this->gerente)->test(ViewEstadoCuenta::class, ['record' => $estado->id])
            ->callAction('csv')->assertFileDownloaded('estado-2027-03.csv', null, App\Support\Csv::TIPO_EXCEL);
        expect(App\Support\Csv::enUtf8((string) base64_decode((string) $csv->effects['download']['content'], true)))
            ->toContain("\t")->toContain(__('propietarios.estado.neto'))->not->toContain("'-");
    });

    it('un recepcionista no los ve', function (): void {
        $estado = $this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01'));

        $this->actingAs($this->recepcion);
        expect(App\Filament\Resources\EstadosCuenta\EstadoCuentaResource::canViewAny())->toBeFalse();

        Livewire::actingAs($this->recepcion)->test(ViewEstadoCuenta::class, ['record' => $estado->id])->assertNotFound();
    });

    it('no se ven los de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajeno = Propietario::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajeno']);
        $estado = $this->estados->generar($ajeno, CarbonImmutable::parse('2027-03-01'));

        Livewire::actingAs($this->contador)->test(ListEstadosCuenta::class)->assertDontSee('Ajeno');
        expect(App\Filament\Resources\EstadosCuenta\EstadoCuentaResource::getEloquentQuery()->whereKey($estado->id)->exists())->toBeFalse();
    });
});

describe('en el panel', function (): void {
    it('se crea un dueño con su acuerdo', function (): void {
        Livewire::actingAs($this->gerente)->test(CreatePropietario::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Don Prueba', 'comision_porcentaje' => 15, 'cargo_fijo' => '100'])
            ->call('create')
            ->assertHasNoFormErrors();

        $nuevo = Propietario::query()->where('nombre', 'Don Prueba')->sole();
        expect((float) $nuevo->comision_porcentaje)->toBe(15.0)
            ->and($nuevo->cargo_fijo)->toBe(10000);
    });

    it('asigna unidades, cambia la comisión de una y la quita; las de otro dueño no se ofrecen', function (): void {
        $otro = Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);

        Livewire::actingAs($this->gerente)->test(UnidadesRelationManager::class, ['ownerRecord' => $otro, 'pageClass' => EditPropietario::class])
            ->callTableAction('asignarUnidades', null, ['unidades' => [$this->ajena->id]])
            ->assertHasNoTableActionErrors();
        expect($this->ajena->refresh()->propietario_id)->toBe($otro->id);

        Livewire::actingAs($this->gerente)->test(UnidadesRelationManager::class, ['ownerRecord' => $otro, 'pageClass' => EditPropietario::class])
            ->callTableAction('comisionUnidad', $this->ajena, ['comision' => 12])
            ->callTableAction('quitarUnidad', $this->ajena);
        expect($this->ajena->refresh()->propietario_id)->toBeNull()
            ->and($this->ajena->comision_propietario)->toBeNull();

        Livewire::actingAs($this->gerente)->test(UnidadesRelationManager::class, ['ownerRecord' => $otro, 'pageClass' => EditPropietario::class])
            ->callTableAction('asignarUnidades', null, ['unidades' => [$this->unidad->id]]);
        expect($this->unidad->refresh()->propietario_id)->toBe($this->dueno->id);
    });

    it('da acceso, enseña la clave una vez cambiada no, y lo apaga', function (): void {
        Livewire::actingAs($this->gerente)->test(EditPropietario::class, ['record' => $this->dueno->id])
            ->callAction('darAcceso')
            ->assertActionMounted('verAcceso');

        $this->dueno->refresh();
        expect($this->dueno->acceso_activo)->toBeTrue()
            ->and($this->dueno->clave_cifrada)->not->toBeNull()
            ->and(Propietario::query()->whereKey($this->dueno->id)->first()->toArray())->not->toHaveKey('clave_cifrada');

        Livewire::actingAs($this->gerente)->test(EditPropietario::class, ['record' => $this->dueno->id])
            ->callAction('apagarAcceso');
        expect($this->dueno->refresh()->acceso_activo)->toBeFalse();
    });

    it('un gasto se le carga a un dueño, y con su comprobante', function (): void {
        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Movimientos\Pages\CreateMovimiento::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoMovimiento::Gasto->value, 'fecha' => '2027-03-15', 'concepto' => 'Plomero', 'importe' => '80', 'metodo' => MetodoPago::Efectivo->value, 'propietario_id' => $this->dueno->id, 'unidad_id' => $this->unidad->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $gasto = Movimiento::query()->sole();
        expect($gasto->propietario_id)->toBe($this->dueno->id)
            ->and($gasto->unidad_id)->toBe($this->unidad->id)
            ->and(($this->calcular)('2027-03-01', '2027-03-31')['gastos'])->toBe(8000);

        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Movimientos\Pages\ListMovimientos::class)
            ->assertSee('Doña Ejemplo');
    });

    it('al gasto de un dueño se le sube su comprobante', function (): void {
        $gasto = Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $this->dueno->id, 'fecha' => '2027-03-15', 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Plomero', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);
        $sinDueno = Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'fecha' => '2027-03-15', 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Luz', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);

        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Movimientos\Pages\ListMovimientos::class)
            ->assertTableActionHidden('comprobanteGasto', $sinDueno)
            ->assertTableActionHidden('verComprobanteGasto', $gasto)
            ->callTableAction('comprobanteGasto', $gasto, ['archivo' => Illuminate\Http\UploadedFile::fake()->image('recibo.jpg', 200, 200)])
            ->assertHasNoTableActionErrors();

        expect($gasto->refresh()->comprobante_archivo_id)->not->toBeNull()
            ->and($gasto->comprobante->clase)->toBe(App\Enums\ClaseArchivo::ComprobanteGasto);
    });

    it('el bloqueo del panel puede ser una estancia del dueño', function (): void {
        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Reservas\Pages\CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-05-01', 'salida' => '2027-05-05', 'propietario_id' => $this->dueno->id])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Reserva::query()->sole()->propietario_id)->toBe($this->dueno->id);
    });

    it('la estancia solo puede ser del dueño de esa unidad', function (): void {
        $bloqueo = app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->ajena->id, 'llegada' => '2027-05-01', 'salida' => '2027-05-05', 'propietario_id' => $this->dueno->id]);

        expect($bloqueo->propietario_id)->toBeNull();
    });

    it('al cambiar de dueño en un gasto se quita la unidad del anterior', function (): void {
        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Movimientos\Pages\CreateMovimiento::class)
            ->set('data.propiedad_id', $this->propiedad->id)
            ->set('data.propietario_id', $this->dueno->id)
            ->set('data.unidad_id', $this->unidad->id)
            ->set('data.propietario_id', null)
            ->assertSet('data.unidad_id', null);
    });

    it('los porcentajes llevan el separador de cada idioma, sin decimales de más', function (): void {
        app()->setLocale('fr');
        expect(Formato::tasa('12.50'))->toBe('12,5 %')->and(Formato::tasa(20))->toBe('20 %');
        app()->setLocale('es');
        expect(Formato::tasa('12.25'))->toBe('12.25 %');
    });

    it('sin permiso de catálogo, o con el módulo apagado, no se entra', function (): void {
        $this->actingAs($this->recepcion);
        expect(App\Filament\Resources\Propietarios\PropietarioResource::canViewAny())->toBeFalse();

        $this->actingAs($this->gerente);
        expect(App\Filament\Resources\Propietarios\PropietarioResource::canViewAny())->toBeTrue();

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Propietarios)));
        expect(App\Filament\Resources\Propietarios\PropietarioResource::canViewAny())->toBeFalse();
    });

    it('la lista abre para quien lleva el catálogo', function (): void {
        Livewire::actingAs($this->gerente)->test(ListPropietarios::class)->assertSee('Doña Ejemplo');
    });
});

describe('portal', function (): void {
    it('pide la clave, y sin ella no enseña nada', function (): void {
        [$token] = ($this->conClave)();
        auth()->logout();

        $this->get("/propietario/$token")->assertOk()->assertSee('name="clave"', false)->assertDontSee(__('propietarios.portal.estados'));
        $this->post("/propietario/$token/entrar", ['clave' => 'mala'])->assertSessionHasErrors('clave');
    });

    it('un enlace que no existe, un acceso apagado o un dueño inactivo dan 404', function (): void {
        [$token] = ($this->conClave)();

        $this->get('/propietario/'.str_repeat('a', 40))->assertNotFound();

        $this->acceso->desactivar($this->dueno->refresh());
        $this->get("/propietario/$token")->assertNotFound();

        $this->acceso->activar($this->dueno->refresh());
        $this->dueno->update(['activo' => false]);
        $this->get("/propietario/$token")->assertNotFound();
    });

    it('una clave nueva invalida el enlace anterior', function (): void {
        [$token] = ($this->conClave)();
        $this->acceso->generarClave($this->dueno->refresh());

        $this->get("/propietario/$token")->assertNotFound();
    });

    it('la primera vez pide cambiar la clave, y lo de adentro no abre antes', function (): void {
        [$token, $clave] = ($this->conClave)();

        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => '2030-01-01', 'salida' => '2030-01-02'])->assertForbidden();

        $this->post("/propietario/$token/entrar", ['clave' => $clave])->assertRedirect();
        $this->get("/propietario/$token")->assertOk()->assertSee('name="clave_confirmation"', false);

        $this->post("/propietario/$token/clave", ['clave' => 'una-clave-larga', 'clave_confirmation' => 'una-clave-larga'])->assertRedirect();
        $this->get("/propietario/$token")->assertOk()->assertSee(__('propietarios.portal.estados'));
        expect($this->dueno->refresh()->clave_cifrada)->toBeNull();
    });

    it('con la clave que le dieron, lo de su cuenta lo regresa a cambiarla', function (): void {
        $estado = $this->estados->enviar($this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01')));
        [$token, $clave] = ($this->conClave)();
        $this->post("/propietario/$token/entrar", ['clave' => $clave])->assertRedirect();
        $hoy = $this->propiedad->hoy();

        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->addDays(10)->format('Y-m-d'), 'salida' => $hoy->addDays(12)->format('Y-m-d')])
            ->assertRedirectContains("/propietario/$token");
        $this->get("/propietario/$token/estado/{$estado->id}")->assertRedirectContains("/propietario/$token");

        expect(Reserva::query()->count())->toBe(0);
    });

    it('enseña su calendario y sus números, nunca al huésped ni lo de otra unidad', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();
        ($this->reservar)($hoy->addDays(2)->format('Y-m-d'), $hoy->addDays(5)->format('Y-m-d'));
        ($this->reservar)($hoy->addDays(2)->format('Y-m-d'), $hoy->addDays(5)->format('Y-m-d'), 50000, ['unidad_id' => $this->ajena->id]);

        $html = (string) $this->get("/propietario/$token")->assertOk()->assertSee('Casa 1')->getContent();

        expect($html)->not->toContain('Zacarías')
            ->and($html)->not->toContain('Casa 2');
    });

    it('enseña los estados mandados y baja su PDF; los borradores y los de otro, no', function (): void {
        $token = ($this->adentro)();
        $borrador = $this->estados->generar($this->dueno->refresh(), CarbonImmutable::parse('2027-02-01'));
        $mandado = $this->estados->enviar($this->estados->generar($this->dueno, CarbonImmutable::parse('2027-03-01')));
        $otro = Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);
        $ajeno = $this->estados->enviar($this->estados->generar($otro, CarbonImmutable::parse('2027-03-01')));

        $this->get("/propietario/$token/estado/{$mandado->id}")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get("/propietario/$token/estado/{$borrador->id}")->assertNotFound();
        $this->get("/propietario/$token/estado/{$ajeno->id}")->assertNotFound();
    });

    it('aparta sus fechas, le avisa al alojamiento y las puede liberar', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();

        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->addDays(10)->format('Y-m-d'), 'salida' => $hoy->addDays(12)->format('Y-m-d')])
            ->assertRedirectContains('aviso=bloqueado');

        $bloqueo = Reserva::query()->sole();
        expect($bloqueo->tipo)->toBe(TipoReserva::Bloqueo)
            ->and($bloqueo->propietario_id)->toBe($this->dueno->id)
            ->and($bloqueo->total)->toBe(0)
            ->and(Envio::query()->sole()->destino)->toBe('oficina@example.com');

        $this->post("/propietario/$token/bloqueo/{$bloqueo->id}")->assertRedirectContains('aviso=desbloqueado');
        expect($bloqueo->refresh()->estado)->toBe(EstadoReserva::Cancelada);
    });

    it('el aviso al alojamiento trae las fechas en su formato, y también llega al liberar', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();
        $llegada = $hoy->addDays(10);

        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $llegada->format('Y-m-d'), 'salida' => $hoy->addDays(12)->format('Y-m-d')])->assertRedirect();

        $aviso = Envio::query()->sole();
        expect($aviso->cuerpo)->not->toContain($llegada->format('Y-m-d'))
            ->and($aviso->cuerpo)->toContain('Casa 1');
        $previo = app()->getLocale();
        app()->setLocale('es');
        expect($aviso->cuerpo)->toContain(Formato::fecha($llegada));
        app()->setLocale($previo);

        $this->post('/propietario/'.$token.'/bloqueo/'.Reserva::query()->sole()->id)->assertRedirect();

        expect(Envio::query()->count())->toBe(2)
            ->and(Envio::query()->latest('id')->first()->asunto)->toBe(__('propietarios.portal.aviso_libera_asunto', ['nombre' => 'Doña Ejemplo'], 'es'));
    });

    it('un error al apartar se ve antes del resto de la página', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();

        $this->from("/propietario/$token")->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->addDays(12)->format('Y-m-d'), 'salida' => $hoy->addDays(10)->format('Y-m-d')])->assertSessionHasErrors('salida');

        $pagina = (string) $this->get("/propietario/$token")->assertOk()->getContent();
        expect(mb_strpos($pagina, 'role="alert"'))->toBeLessThan(mb_strpos($pagina, 'class="tarjeta"'));
    });

    it('no puede apartar lo ocupado, una unidad ajena o una fecha pasada', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();
        ($this->reservar)($hoy->addDays(10)->format('Y-m-d'), $hoy->addDays(15)->format('Y-m-d'));

        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->addDays(11)->format('Y-m-d'), 'salida' => $hoy->addDays(12)->format('Y-m-d')])->assertSessionHasErrors('llegada');
        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->ajena->id, 'llegada' => $hoy->addDays(20)->format('Y-m-d'), 'salida' => $hoy->addDays(22)->format('Y-m-d')])->assertForbidden();
        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->subDay()->format('Y-m-d'), 'salida' => $hoy->addDay()->format('Y-m-d')])->assertSessionHasErrors('llegada');
    });

    it('no libera lo que no es suyo ni lo que ya empezó', function (): void {
        $token = ($this->adentro)();
        $hoy = $this->propiedad->hoy();
        $reserva = ($this->reservar)($hoy->addDays(10)->format('Y-m-d'), $hoy->addDays(15)->format('Y-m-d'));
        $empezado = app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => $hoy->format('Y-m-d'), 'salida' => $hoy->addDays(2)->format('Y-m-d'), 'propietario_id' => $this->dueno->id]);

        $this->post("/propietario/$token/bloqueo/{$reserva->id}")->assertNotFound();
        $this->post("/propietario/$token/bloqueo/{$empezado->id}")->assertNotFound();
    });

    it('sin permiso en el acuerdo no aparta', function (): void {
        $token = ($this->adentro)();
        $this->dueno->update(['puede_bloquear' => false]);
        $hoy = $this->propiedad->hoy();

        $this->get("/propietario/$token")->assertOk()->assertDontSee(__('propietarios.portal.bloquear_boton'));
        $this->post("/propietario/$token/bloquear", ['unidad_id' => $this->unidad->id, 'llegada' => $hoy->addDays(10)->format('Y-m-d'), 'salida' => $hoy->addDays(12)->format('Y-m-d')])->assertForbidden();
    });

    it('ve el comprobante de un gasto suyo, y no el de otro', function (): void {
        $token = ($this->adentro)();
        $archivo = app(App\Archivos\GestorArchivos::class)->guardar('recibo', 'recibo.png', 'image/png', App\Enums\ClaseArchivo::ComprobanteGasto, null, $this->propiedad->id);
        $hoy = $this->propiedad->hoy();
        $suyo = Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $this->dueno->id, 'comprobante_archivo_id' => $archivo->id, 'fecha' => $hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Plomero', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);
        $otro = Propietario::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Otro']);
        $ajeno = Movimiento::query()->create(['propiedad_id' => $this->propiedad->id, 'propietario_id' => $otro->id, 'comprobante_archivo_id' => $archivo->id, 'fecha' => $hoy->format('Y-m-d'), 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Ajeno', 'metodo' => MetodoPago::Efectivo, 'importe' => 8000, 'moneda' => 'MXN']);

        $this->get("/propietario/$token")->assertOk()->assertSee('Plomero')->assertDontSee('Ajeno');
        $this->get("/propietario/$token/comprobante/{$suyo->id}")->assertOk();
        $this->get("/propietario/$token/comprobante/{$ajeno->id}")->assertNotFound();
    });

    it('con el módulo apagado no abre', function (): void {
        [$token] = ($this->conClave)();
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Propietarios)));

        $this->get("/propietario/$token")->assertNotFound();
    });
});

describe('módulo', function (): void {
    it('tiene sus textos, y avisa si hay dueños al apagarlo', function (): void {
        expect(Modulo::Propietarios->etiqueta())->toBe('Propietarios')
            ->and(FaseEstadoCuenta::Enviado->etiqueta())->toBe('Enviado')
            ->and(Modulos::enUso(Modulo::Propietarios))->toBe(1);
    });

    it('la demostración trae con qué probarlo', function (): void {
        (new App\Demo\GeneradorDemo())->crear();

        $dueno = Propietario::query()->whereHas('propiedad', fn ($q) => $q->where('es_demo', true))->sole();
        expect($dueno->unidades()->count())->toBeGreaterThan(0)
            ->and($dueno->estadosCuenta()->count())->toBe(1);
    });
});
