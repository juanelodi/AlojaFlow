<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Dinero\GestorPoliticas;
use App\Enums\BaseCargo;
use App\Enums\EstadoFactura;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoDocumentoFiscal;
use App\Enums\TipoTarea;
use App\Facturacion\ConectorPac;
use App\Facturacion\ErrorFacturacion;
use App\Facturacion\GestorFacturas;
use App\Filament\Pages\Informes as PaginaInformes;
use App\Filament\Resources\EntidadesFiscales\Pages\CreateEntidadFiscal;
use App\Filament\Resources\Facturas\FacturaResource;
use App\Filament\Resources\Facturas\Pages\ListFacturas;
use App\Filament\Resources\Politicas\Pages\CreatePolitica;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Models\Canal;
use App\Models\CargoReserva;
use App\Models\EntidadFiscal;
use App\Models\Factura;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\MarcaNoShow;
use App\Models\Movimiento;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\ReceptorFiscal;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
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

describe('facturación', function () {
    beforeEach(function () {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '002', 'nombre' => 'IVA', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 16, 'incluido_en_precio' => true, 'orden' => 1]);
        $this->entidad = EntidadFiscal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Posada SA de CV', 'pais' => 'MX', 'identificador_fiscal' => 'EKU9003173C9', 'regimen_fiscal' => '601', 'codigo_postal' => '01000']);
        $this->serie = SerieFacturacion::query()->create(['entidad_fiscal_id' => $this->entidad->id, 'serie' => 'A', 'siguiente_numero' => 41])->refresh();
        $this->facturas = app(GestorFacturas::class);
        $this->receptor = ['identificador_fiscal' => 'xaxx010101000', 'nombre' => 'Ana Ejemplo', 'regimen_fiscal' => '616', 'codigo_postal' => '06000', 'uso' => 'S01', 'correo' => 'ana@example.com'];
    });

    it('emite con número seguido, impuestos que cuadran y copia de los datos', function () {
        $reserva = ($this->reservar)(1, 3);

        $factura = $this->facturas->emitir($reserva, $this->serie, $this->receptor, '04', 'PUE');

        expect($factura->folio())->toBe('A-41')
            ->and($this->serie->refresh()->siguiente_numero)->toBe(42)
            ->and($factura->subtotal)->toBe(100000)
            ->and($factura->impuestos)->toBe(16000)
            ->and($factura->total)->toBe(116000)
            ->and($factura->receptor['nombre'])->toBe('Ana Ejemplo')
            ->and($factura->reservas()->first()->id)->toBe($reserva->id)
            ->and($factura->pdf_archivo_id)->not->toBeNull()->and($factura->xml_archivo_id)->not->toBeNull()
            ->and(ReceptorFiscal::query()->first()->identificador_fiscal)->toBe('XAXX010101000');

        // Cambiar los datos fiscales después no cambia lo emitido.
        $this->entidad->update(['nombre' => 'Otro nombre']);
        expect($factura->refresh()->emisor['nombre'])->toBe('Posada SA de CV');
        expect(app(GestorArchivos::class)->contenido($factura->pdf))->toStartWith('%PDF');
    });

    it('la suma cuadra con el total aunque haya redondeos y cargos', function () {
        $reserva = ($this->reservar)(1, 3, 100033);
        CargoReserva::query()->create(['reserva_id' => $reserva->id, 'nombre' => 'Desayuno', 'cantidad' => 1, 'importe' => 33333]);

        $factura = $this->facturas->emitir($reserva, $this->serie, $this->receptor, '04', 'PUE');

        expect($factura->lineas)->toHaveCount(2)
            ->and($factura->subtotal + $factura->impuestos)->toBe(100033)
            ->and($factura->lineas[1]->concepto)->toBe('Desayuno');
    });

    it('genera un CFDI 4.0 con la estructura que pide el SAT y sin timbrar', function () {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'ISH', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 3, 'incluido_en_precio' => true, 'orden' => 2]);
        $factura = $this->facturas->emitir(($this->reservar)(1, 3, 119000), $this->serie, $this->receptor, '04', 'PUE');

        $xml = new SimpleXMLElement(app(GestorArchivos::class)->contenido($factura->xml));
        $xml->registerXPathNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->registerXPathNamespace('implocal', 'http://www.sat.gob.mx/implocal');
        $a = $xml->attributes();

        expect((string) $a['Version'])->toBe('4.0')
            ->and((string) $a['Serie'])->toBe('A')->and((string) $a['Folio'])->toBe('41')
            ->and((string) $a['SubTotal'])->toBe('1000.00')
            ->and((string) $a['Total'])->toBe('1190.00')
            ->and((string) $a['TipoDeComprobante'])->toBe('I')
            ->and((string) $a['Exportacion'])->toBe('01')
            ->and((string) $a['MetodoPago'])->toBe('PUE')->and((string) $a['FormaPago'])->toBe('04')
            ->and((string) $a['LugarExpedicion'])->toBe('01000')
            ->and((string) $a['Sello'])->toBe('')->and((string) $a['NoCertificado'])->toBe('');
        expect((string) $xml->xpath('//cfdi:Emisor')[0]['Rfc'])->toBe('EKU9003173C9')
            ->and((string) $xml->xpath('//cfdi:Receptor')[0]['Rfc'])->toBe('XAXX010101000')
            ->and((string) $xml->xpath('//cfdi:Receptor')[0]['UsoCFDI'])->toBe('S01')
            ->and((string) $xml->xpath('//cfdi:Concepto')[0]['ClaveProdServ'])->toBe('90111800')
            ->and((string) $xml->xpath('//cfdi:Concepto')[0]['ObjetoImp'])->toBe('02')
            ->and((string) $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos')[0]['TotalImpuestosTrasladados'])->toBe('160.00')
            ->and((string) $xml->xpath('//cfdi:Comprobante/cfdi:Impuestos/cfdi:Traslados/cfdi:Traslado')[0]['TasaOCuota'])->toBe('0.160000')
            ->and((string) $xml->xpath('//implocal:TrasladosLocales')[0]['ImpLocTrasladado'])->toBe('ISH')
            ->and((string) $xml->xpath('//implocal:TrasladosLocales')[0]['Importe'])->toBe('30.00')
            ->and((string) $xml->xpath('//implocal:ImpuestosLocales')[0]['TotaldeTraslados'])->toBe('30.00');
    });

    it('rechaza un RFC mal escrito, una reserva ya facturada o datos de otra propiedad', function () {
        $reserva = ($this->reservar)(1, 3);
        expect(fn () => $this->facturas->emitir($reserva, $this->serie, ['identificador_fiscal' => 'NO-VALE'] + $this->receptor))->toThrow(ErrorFacturacion::class, __('dinero.facturas.error_rfc'));
        expect(fn () => $this->facturas->emitir($reserva, $this->serie, ['codigo_postal' => '1'] + $this->receptor))->toThrow(ErrorFacturacion::class, __('dinero.facturas.error_cp'));
        expect($this->serie->refresh()->siguiente_numero)->toBe(41);

        $factura = $this->facturas->emitir($reserva, $this->serie, $this->receptor, '04', 'PUE');
        expect(fn () => $this->facturas->emitir($reserva, $this->serie, $this->receptor))->toThrow(ErrorFacturacion::class, __('dinero.facturas.error_ya_facturada'));

        $this->facturas->cancelar($factura, 'Error en el nombre');
        expect($factura->refresh()->estado)->toBe(EstadoFactura::Cancelada)
            ->and($this->facturas->emitir($reserva, $this->serie, $this->receptor, '04', 'PUE')->folio())->toBe('A-42');

        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
        $ajena = EntidadFiscal::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'X', 'pais' => 'MX', 'identificador_fiscal' => 'X']);
        $serieAjena = SerieFacturacion::query()->create(['entidad_fiscal_id' => $ajena->id, 'serie' => 'Z'])->refresh();
        expect(fn () => $this->facturas->emitir(($this->reservar)(5, 6), $serieAjena, $this->receptor))->toThrow(ErrorFacturacion::class, __('dinero.facturas.error_entidad'));
    });

    it('sin PAC lo dice y lo deja anotado; con uno, queda timbrada', function () {
        $factura = $this->facturas->emitir(($this->reservar)(1, 3), $this->serie, $this->receptor, '04', 'PUE');

        expect(fn () => $this->facturas->timbrar($factura))->toThrow(ErrorFacturacion::class, __('dinero.facturas.sin_pac'));
        expect($factura->refresh()->estado)->toBe(EstadoFactura::Emitida)
            ->and($factura->eventos()->first()->estado)->toBe('rechazado');

        $this->app->bind('pac-de-prueba', fn () => new class () implements ConectorPac {
            public function timbrar(string $xml, EntidadFiscal $entidad): array
            {
                return ['xml' => str_replace('Sello=""', 'Sello="ABC"', $xml), 'uuid' => 'AAAA-BBBB'];
            }

            public function cancelar(string $uuid, string $motivo, EntidadFiscal $entidad): void
            {
            }
        });
        config(['alojaflow.pac' => 'pac-de-prueba']);

        $this->facturas->timbrar($factura->refresh());
        expect($factura->refresh()->estado)->toBe(EstadoFactura::Registrada)
            ->and($factura->uuid_fiscal)->toBe('AAAA-BBBB')
            ->and(app(GestorArchivos::class)->contenido($factura->xml))->toContain('Sello="ABC"');
    });

    it('en otro país emite un recibo sin XML', function () {
        $this->propiedad->update(['paquete_pais' => PaquetePais::Generico]);
        $this->serie->update(['tipo_documento' => TipoDocumentoFiscal::Recibo]);

        $factura = $this->facturas->emitir(($this->reservar)(1, 3)->load('propiedad'), $this->serie->refresh(), ['identificador_fiscal' => 'ABC', 'nombre' => 'Ana']);

        expect($factura->xml_archivo_id)->toBeNull()->and($factura->pdf_archivo_id)->not->toBeNull();
    });

    it('se factura desde la reserva y se exporta para el contador', function () {
        $reserva = ($this->reservar)(1, 3);

        Livewire::test(ViewReserva::class, ['record' => $reserva->getRouteKey()])
            ->callAction('facturar', $this->receptor + ['serie_id' => $this->serie->id, 'forma_pago' => '04', 'metodo_pago' => 'PUE'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('dinero.facturas.emitida_ok', ['folio' => 'A-41']));

        $respuesta = Livewire::test(ListFacturas::class)
            ->assertCanSeeTableRecords(Factura::query()->get())
            ->callAction('exportar', ['desde' => $this->hoy->subDay()->format('Y-m-d'), 'hasta' => $this->hoy->addDay()->format('Y-m-d')]);
        $respuesta->assertFileDownloaded();

        $contador = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
        $this->actingAs($contador);
        expect(FacturaResource::canViewAny())->toBeTrue();
        $this->get(route('archivos.ver', Factura::query()->first()->pdf_archivo_id))->assertOk();

        $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
        $this->actingAs($limpieza);
        expect(FacturaResource::canViewAny())->toBeFalse();
        $this->get(route('archivos.ver', Factura::query()->first()->pdf_archivo_id))->assertNotFound();
    });

    it('una propiedad con facturas no se borra; la demostración sí', function () {
        $this->facturas->emitir(($this->reservar)(1, 3), $this->serie, $this->receptor, '04', 'PUE');
        $borrador = app(App\Propiedades\BorradorPropiedad::class);

        expect(fn () => $borrador->borrar($this->propiedad))->toThrow(App\Propiedades\ErrorIndependizar::class, __('ui.propiedades.error_con_facturas'));
        expect(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeTrue();

        $borrador->borrar($this->propiedad, conFacturas: true);
        expect(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeFalse();
    });

    it('reconoce el IVA aunque su código sea IVA', function () {
        Impuesto::query()->update(['codigo' => 'IVA']);
        $factura = $this->facturas->emitir(($this->reservar)(1, 3), $this->serie, $this->receptor, '04', 'PUE');

        expect(app(GestorArchivos::class)->contenido($factura->xml))->toContain('Impuesto="002"')->toContain('TotalImpuestosTrasladados="160.00"');
    });

    it('los datos fiscales se dan de alta en el panel', function () {
        Livewire::test(CreateEntidadFiscal::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Nueva', 'identificador_fiscal' => 'AAA010101AAA', 'pais' => 'MX', 'regimen_fiscal' => '626', 'codigo_postal' => '01000', 'modo' => 'pruebas', 'series' => [['serie' => 'B', 'tipo_documento' => 'factura', 'siguiente_numero' => 1, 'activa' => true]]])
            ->call('create')->assertHasNoFormErrors();
        expect(EntidadFiscal::query()->where('nombre', 'Nueva')->first()->series()->first()->serie)->toBe('B');
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
