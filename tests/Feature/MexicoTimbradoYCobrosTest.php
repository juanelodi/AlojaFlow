<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoFactura;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoFiscal;
use App\Enums\ModoPropiedad;
use App\Enums\MotivoCancelacionCfdi;
use App\Enums\PaquetePais;
use App\Facturacion\ConectorSw;
use App\Facturacion\DatosTimbre;
use App\Facturacion\ErrorFacturacion;
use App\Facturacion\FabricaPac;
use App\Facturacion\GestorFacturas;
use App\Facturacion\SinPac;
use App\Filament\Pages\MotorDeReservas;
use App\Filament\Resources\EntidadesFiscales\Pages\ListEntidadesFiscales;
use App\Filament\Resources\Facturas\Pages\ListFacturas;
use App\Models\EntidadFiscal;
use App\Models\EventoFiscal;
use App\Models\Factura;
use App\Models\Huesped;
use App\Models\PagoEnLinea;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

function cfdiTimbrado(string $uuid = 'AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB'): string
{
    return '<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Version="4.0" NoCertificado="30001000000500003416" Total="1160.00">'
        .'<cfdi:Emisor Rfc="EKU9003173C9"/><cfdi:Receptor Rfc="XAXX010101000"/><cfdi:Complemento>'
        .'<tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" Version="1.1" UUID="'.$uuid.'" FechaTimbrado="2026-11-05T10:00:00" RfcProvCertif="SPR190613I52" SelloCFD="SELLOEMISOR12345678" NoCertificadoSAT="30001000000500003456" SelloSAT="SELLOSAT"/>'
        .'</cfdi:Complemento></cfdi:Comprobante>';
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    Http::preventStrayRequests();
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es', 'paquete_pais' => PaquetePais::Mexico,
        'ajustes' => ['motor' => ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'activa' => true]);
    $this->hoy = $this->propiedad->hoy();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->contador = User::query()->create(['name' => 'Cont', 'email' => 'cont@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
});

describe('timbrado', function (): void {
    beforeEach(function (): void {
        $this->entidad = EntidadFiscal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Posada SA de CV', 'pais' => 'MX', 'identificador_fiscal' => 'EKU9003173C9', 'regimen_fiscal' => '601', 'codigo_postal' => '01000', 'modo' => ModoFiscal::Pruebas])->refresh();
        $this->serie = SerieFacturacion::query()->create(['entidad_fiscal_id' => $this->entidad->id, 'serie' => 'A', 'siguiente_numero' => 41])->refresh();
        $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Prueba']);
        $this->reservar = fn (int $dias = 5): Reserva => app(GestorReservas::class)->crear($this->propiedad, [
            'unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->addDays($dias)->format('Y-m-d'), 'salida' => $this->hoy->addDays($dias + 2)->format('Y-m-d'),
            'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 116000,
        ]);
        $this->facturas = app(GestorFacturas::class);
        $this->emitir = fn (int $dias = 5): Factura => $this->facturas->emitir(($this->reservar)($dias), $this->serie, ['identificador_fiscal' => 'XAXX010101000', 'nombre' => 'Ana', 'regimen_fiscal' => '616', 'codigo_postal' => '01000', 'uso' => 'S01', 'correo' => 'ana@example.com'], '04', 'PUE');
        $this->conPac = function (string $token = 'token-de-prueba'): void {
            $this->entidad->update(['configuracion' => ['pac' => FabricaPac::SW, 'pac_token' => $token]]);
        };
        $this->exito = fn (string $uuid = 'AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB') => Http::response(['status' => 'success', 'data' => ['cfdi' => cfdiTimbrado($uuid), 'uuid' => $uuid, 'qrCode' => base64_encode('png')]]);
    });

    it('sin conectar nada, no se timbra y lo dice', function (): void {
        expect(app(FabricaPac::class)->de($this->entidad))->toBeInstanceOf(SinPac::class);

        expect(fn () => $this->facturas->timbrar(($this->emitir)()))->toThrow(ErrorFacturacion::class, __('dinero.facturas.sin_pac'));
    });

    it('manda el XML a SW con su token y guarda lo que devuelve', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)()]);
        $factura = ($this->emitir)();
        $pdfAnterior = $factura->pdf_archivo_id;

        $this->facturas->timbrar($factura);

        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion->url() === 'https://services.test.sw.com.mx/cfdi33/issue/v4'
            && $peticion->hasHeader('Authorization', 'Bearer token-de-prueba')
            && $peticion->isMultipart() && str_contains($peticion->body(), 'name="xml"') && str_contains($peticion->body(), 'cfdi:Comprobante'));
        $factura->refresh();
        expect($factura->estado)->toBe(EstadoFactura::Registrada)
            ->and($factura->uuid_fiscal)->toBe('AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB')
            ->and($factura->qr_timbrado)->toBe(base64_encode('png'))
            ->and($factura->pdf_archivo_id)->not->toBe($pdfAnterior)
            ->and(App\Models\Archivo::query()->whereKey($pdfAnterior)->exists())->toBeFalse()
            ->and(app(App\Archivos\GestorArchivos::class)->contenido($factura->xml))->toContain('TimbreFiscalDigital');
    });

    it('en producción usa la dirección real', function (): void {
        ($this->conPac)();
        $this->entidad->update(['modo' => ModoFiscal::Produccion]);
        Http::fake(['services.sw.com.mx/cfdi33/issue/v4' => ($this->exito)()]);

        $this->facturas->timbrar(($this->emitir)());

        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion->url() === 'https://services.sw.com.mx/cfdi33/issue/v4');
    });

    it('si el PAC la rechaza dice por qué, la deja como estaba y lo anota, sin el token', function (): void {
        ($this->conPac)('token-secreto');
        Http::fake(['services.test.sw.com.mx/*' => Http::response(['status' => 'error', 'message' => 'CFDI40102', 'messageDetail' => 'Sello inválido'], 400)]);
        $factura = ($this->emitir)();

        expect(fn () => $this->facturas->timbrar($factura))->toThrow(ErrorFacturacion::class, 'CFDI40102 Sello inválido');

        expect($factura->refresh()->estado)->toBe(EstadoFactura::Emitida)
            ->and($factura->uuid_fiscal)->toBeNull();
        $evento = EventoFiscal::query()->where('factura_id', $factura->id)->where('estado', 'rechazado')->sole();
        expect((string) json_encode($evento->respuesta))->toContain('CFDI40102')->not->toContain('token-secreto');
    });

    it('si no contesta, no se cuelga: lo dice', function (): void {
        ($this->conPac)();
        Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('sin red'));

        expect(fn () => $this->facturas->timbrar(($this->emitir)()))->toThrow(ErrorFacturacion::class, __('dinero.pac.sin_conexion'));
    });

    it('una respuesta sin el CFDI no marca nada como timbrado', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => Http::response(['status' => 'success', 'data' => []])]);
        $factura = ($this->emitir)();

        expect(fn () => $this->facturas->timbrar($factura))->toThrow(ErrorFacturacion::class, __('dinero.pac.respuesta_rara'));

        expect($factura->refresh()->uuid_fiscal)->toBeNull();
    });

    it('un QR que no es base64 no llega al PDF', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => Http::response(['status' => 'success', 'data' => ['cfdi' => cfdiTimbrado(), 'uuid' => 'U-1', 'qrCode' => '"><script>alert(1)</script>']])]);
        $factura = ($this->emitir)();

        $this->facturas->timbrar($factura);

        expect($factura->refresh()->qr_timbrado)->toBeNull();
    });

    it('dos clics no timbran dos veces', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => ($this->exito)()]);
        $factura = ($this->emitir)();
        $repetida = Factura::query()->findOrFail($factura->id);

        $candado = Illuminate\Support\Facades\Cache::lock('timbrado:factura:'.$factura->id, 120);
        $candado->get();
        expect(fn () => $this->facturas->timbrar($factura))->toThrow(ErrorFacturacion::class, __('dinero.pac.en_proceso'));
        Http::assertNothingSent();
        $candado->release();

        $this->facturas->timbrar($factura);
        $this->facturas->timbrar($repetida);

        Http::assertSentCount(1);
    });

    it('sin candado de por medio, se puede volver a intentar tras un rechazo', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => Http::sequence()->push(['status' => 'error', 'message' => 'Falla'], 500)->push(['status' => 'success', 'data' => ['cfdi' => cfdiTimbrado(), 'uuid' => 'U-2']])]);
        $factura = ($this->emitir)();

        expect(fn () => $this->facturas->timbrar($factura))->toThrow(ErrorFacturacion::class);
        $this->facturas->timbrar($factura);

        expect($factura->refresh()->uuid_fiscal)->toBe('U-2');
    });

    it('la impresión lleva lo que exige un CFDI', function (): void {
        $timbre = DatosTimbre::de(cfdiTimbrado());

        expect($timbre['uuid'])->toBe('AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB')
            ->and($timbre['certificado_emisor'])->toBe('30001000000500003416')
            ->and($timbre['cadena'])->toBe('||1.1|AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB|2026-11-05T10:00:00|SPR190613I52|SELLOEMISOR12345678|30001000000500003456||')
            ->and($timbre['verificacion'])->toContain('re=EKU9003173C9')->toContain('rr=XAXX010101000')->toContain('tt=1160.00')->toContain('fe=12345678');
        expect(DatosTimbre::de('no es xml'))->toBeNull()
            ->and(DatosTimbre::de('<a/>'))->toBeNull()
            ->and(DatosTimbre::de(str_replace('SelloCFD=', 'Leyenda="Hola" SelloCFD=', cfdiTimbrado()))['cadena'])->toContain('|SPR190613I52|Hola|SELLOEMISOR12345678|');

        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => ($this->exito)()]);
        $factura = ($this->emitir)();
        $this->facturas->timbrar($factura);
        $factura->refresh();
        $html = view('documentos.factura', ['factura' => $factura, 'impuestos' => [], 'sinValidez' => null, 'timbre' => $timbre])->render();
        expect($html)->toContain('||1.1|')->toContain('data:image/png;base64,')->toContain(__('dinero.pac.representacion'));
    });

    it('cancelar una timbrada pide la clave y la manda al SAT', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)(),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'success', 'data' => ['acuse' => '<Acuse/>', 'uuid' => ['X' => '201']]]),
        ]);
        $factura = ($this->emitir)();
        $this->facturas->timbrar($factura);
        $factura->refresh();

        expect(fn () => $this->facturas->cancelar($factura, 'porque sí'))->toThrow(ErrorFacturacion::class, __('dinero.cancelacion.falta_motivo'));

        $this->facturas->cancelar($factura, '', MotivoCancelacionCfdi::NoSeRealizo);

        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion->method() === 'POST' && $peticion->url() === 'https://services.test.sw.com.mx/cfdi33/cancel/EKU9003173C9/AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB/03');
        $factura->refresh();
        expect($factura->estado)->toBe(EstadoFactura::Cancelada)
            ->and($factura->motivo_cancelacion_clave)->toBe('03')
            ->and(EventoFiscal::query()->where('factura_id', $factura->id)->where('tipo', 'cancelacion')->sole()->respuesta)->toMatchArray(['acuse' => '<Acuse/>', 'codigo' => '201', 'motivo' => '03']);
    });

    it('una cancelación aceptada no falla porque la respuesta venga corta', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)(),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'success', 'data' => ['uuid' => []]]),
        ]);
        $factura = ($this->emitir)();
        $this->facturas->timbrar($factura);

        $this->facturas->cancelar($factura->refresh(), '', MotivoCancelacionCfdi::NoSeRealizo);

        expect($factura->refresh()->estado)->toBe(EstadoFactura::Cancelada);
    });

    it('el PDF de una cancelada lo dice, y el de una timbrada ya no dice «sin timbrar»', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)(),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'success', 'data' => ['uuid' => ['X' => '201']]]),
        ]);
        $avisos = [];
        Illuminate\Support\Facades\View::composer('documentos.factura', function ($vista) use (&$avisos): void {
            $avisos[] = $vista->getData()['sinValidez'];
        });
        $factura = ($this->emitir)();
        expect(end($avisos))->toBe(__('dinero.facturas.sin_timbrar'));

        $this->facturas->timbrar($factura);
        expect(end($avisos))->toBeNull();
        $pdf = $factura->refresh()->pdf_archivo_id;

        $this->facturas->cancelar($factura, '', MotivoCancelacionCfdi::NoSeRealizo);

        expect(end($avisos))->toContain(__('dinero.cancelacion.aviso_pdf', ['fecha' => App\Support\Formato::momento($factura->refresh()->cancelada_en, 'America/Mexico_City')]))
            ->and($factura->pdf_archivo_id)->not->toBe($pdf)
            ->and(App\Models\Archivo::query()->whereKey($pdf)->exists())->toBeFalse();
    });

    it('si no se puede rehacer el PDF, lo fiscal sigue hecho', function (): void {
        ($this->conPac)();
        Http::fake(['services.test.sw.com.mx/*' => ($this->exito)()]);
        $factura = ($this->emitir)();
        Illuminate\Support\Facades\View::composer('documentos.factura', fn () => throw new RuntimeException('sin memoria'));

        $this->facturas->timbrar($factura);

        expect($factura->refresh()->estado)->toBe(EstadoFactura::Registrada)
            ->and($factura->uuid_fiscal)->not->toBeNull();
    });

    it('el motivo 01 exige la que la sustituye y la manda', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)(),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'success', 'data' => ['uuid' => ['X' => '201']]]),
        ]);
        $factura = ($this->emitir)();
        $this->facturas->timbrar($factura);
        $factura->refresh();

        expect(fn () => $this->facturas->cancelar($factura, '', MotivoCancelacionCfdi::ConErroresConRelacion))->toThrow(ErrorFacturacion::class, __('dinero.cancelacion.falta_sustituta'));
        expect($factura->refresh()->estado)->toBe(EstadoFactura::Registrada);

        $this->facturas->cancelar($factura, '', MotivoCancelacionCfdi::ConErroresConRelacion, 'CCCC-DDDD');

        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/cfdi33/cancel/EKU9003173C9/AAAAAAAA-1111-2222-3333-BBBBBBBBBBBB/01/CCCC-DDDD'));
        expect($factura->refresh()->folio_sustitucion)->toBe('CCCC-DDDD');
    });

    it('si el SAT rechaza la cancelación, sigue vigente', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => ($this->exito)(),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'error', 'message' => 'CACFDI33', 'messageDetail' => 'No cancelable'], 400),
        ]);
        $factura = ($this->emitir)();
        $this->facturas->timbrar($factura);

        expect(fn () => $this->facturas->cancelar($factura->refresh(), '', MotivoCancelacionCfdi::NoSeRealizo))->toThrow(ErrorFacturacion::class, 'No cancelable');

        expect($factura->refresh()->estado)->toBe(EstadoFactura::Registrada);
    });

    it('una sin timbrar se cancela como siempre, con su motivo escrito', function (): void {
        $factura = ($this->emitir)();

        $this->facturas->cancelar($factura, 'Se equivocó el nombre');

        expect($factura->refresh()->estado)->toBe(EstadoFactura::Cancelada)
            ->and($factura->motivo_cancelacion)->toBe('Se equivocó el nombre');
    });

    it('la lista pide el motivo del SAT y, con el 01, a cuál sustituye', function (): void {
        ($this->conPac)();
        Http::fake([
            'services.test.sw.com.mx/cfdi33/issue/v4' => Http::sequence()->push(['status' => 'success', 'data' => ['cfdi' => cfdiTimbrado('UUID-1'), 'uuid' => 'UUID-1']])->push(['status' => 'success', 'data' => ['cfdi' => cfdiTimbrado('UUID-2'), 'uuid' => 'UUID-2']]),
            'services.test.sw.com.mx/cfdi33/cancel/*' => Http::response(['status' => 'success', 'data' => ['uuid' => ['X' => '201']]]),
        ]);
        $primera = ($this->emitir)(5);
        $segunda = ($this->emitir)(10);
        $this->facturas->timbrar($primera);
        $this->facturas->timbrar($segunda);

        Livewire::actingAs($this->contador)->test(ListFacturas::class)
            ->callTableAction('cancelar', $primera->refresh(), ['clave' => '01', 'sustituta' => 'UUID-2'])
            ->assertHasNoTableActionErrors();

        expect($primera->refresh()->folio_sustitucion)->toBe('UUID-2')
            ->and($primera->estado)->toBe(EstadoFactura::Cancelada)
            ->and($segunda->refresh()->estado)->toBe(EstadoFactura::Registrada);
    });

    it('conectar guarda el token solo si funciona, cifrado', function (): void {
        Http::fake(['api.test.sw.com.mx/management/v2/api/users/balance' => Http::response(['status' => 'error', 'message' => 'Token inválido'], 401)]);

        Livewire::actingAs($this->contador)->test(ListEntidadesFiscales::class)
            ->callTableAction('conectarPac', $this->entidad, ['pac' => FabricaPac::SW, 'token' => 'malo']);

        expect($this->entidad->refresh()->configuracion)->not->toHaveKey('pac_token');
    });

    it('con un token bueno queda conectada, y se puede quitar', function (): void {
        Http::fake(['api.test.sw.com.mx/management/v2/api/users/balance' => Http::response(['status' => 'success', 'data' => ['stampsBalance' => 40]])]);

        Livewire::actingAs($this->contador)->test(ListEntidadesFiscales::class)
            ->callTableAction('conectarPac', $this->entidad, ['pac' => FabricaPac::SW, 'token' => 'bueno-123'])
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('dinero.pac.conectado'));

        $this->entidad->refresh();
        expect($this->entidad->configuracion)->toMatchArray(['pac' => FabricaPac::SW, 'pac_token' => 'bueno-123'])
            ->and(DB::table('entidades_fiscales')->where('id', $this->entidad->id)->value('configuracion'))->not->toContain('bueno-123')
            ->and(app(FabricaPac::class)->de($this->entidad))->toBeInstanceOf(ConectorSw::class);

        Livewire::actingAs($this->contador)->test(ListEntidadesFiscales::class)
            ->callTableAction('conectarPac', $this->entidad, ['pac' => null])
            ->assertHasNoTableActionErrors();

        expect($this->entidad->refresh()->configuracion)->not->toHaveKey('pac_token');
    });

    it('volver a conectar sin escribir el token conserva el que hay', function (): void {
        ($this->conPac)('guardado-1');
        Http::fake(['api.test.sw.com.mx/*' => Http::response(['status' => 'success', 'data' => []])]);

        Livewire::actingAs($this->contador)->test(ListEntidadesFiscales::class)
            ->callTableAction('conectarPac', $this->entidad->refresh(), ['pac' => FabricaPac::SW, 'token' => ''])
            ->assertHasNoTableActionErrors();

        expect($this->entidad->refresh()->configuracion['pac_token'])->toBe('guardado-1');
        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion->hasHeader('Authorization', 'Bearer guardado-1'));
    });

    it('recepción no conecta nada', function (): void {
        Livewire::actingAs($this->recepcion)->test(ListEntidadesFiscales::class)->assertForbidden();
    });

    it('el token no sale en la auditoría', function (): void {
        Http::fake(['api.test.sw.com.mx/*' => Http::response(['status' => 'success', 'data' => []])]);

        Livewire::actingAs($this->contador)->test(ListEntidadesFiscales::class)
            ->callTableAction('conectarPac', $this->entidad, ['pac' => FabricaPac::SW, 'token' => 'token-que-no-debe-salir']);

        expect((string) json_encode(App\Models\RegistroAuditoria::query()->get()->toArray()))->not->toContain('token-que-no-debe-salir');
    });
});

describe('Stripe en México', function (): void {
    beforeEach(function (): void {
        $this->ajustes = new AjustesMotor($this->propiedad);
        $this->ajustes->guardarLlave('stripe_secreta', 'sk_test_x');
        $this->ajustes->guardarLlave('stripe_webhook', 'whsec_x');
        $this->post('/reservar/posada', ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1'])->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->pagina = '/reservar/posada/r/'.$this->reserva->codigo_publico;
        $this->abrir = fn () => $this->post($this->pagina.'/pagar/stripe');
        $this->opciones = function (array $opciones): void {
            $propiedad = $this->propiedad->refresh();
            $ajustes = (array) $propiedad->ajustes;
            $ajustes['motor'] = $opciones + (array) $ajustes['motor'];
            $propiedad->update(['ajustes' => $ajustes]);
        };
        $this->sesionVoucher = fn (string $estadoPago = 'unpaid', string $tipo = 'oxxo'): array => [
            'id' => 'cs_1', 'status' => 'complete', 'payment_status' => $estadoPago, 'amount_total' => 100000, 'currency' => 'mxn',
            'payment_intent' => ['id' => 'pi_1', 'payment_method' => ['type' => $tipo], 'next_action' => $tipo === 'oxxo'
                ? ['oxxo_display_details' => ['hosted_voucher_url' => 'https://pagos.stripe.test/voucher', 'expires_after' => now()->addDays(3)->getTimestamp()]]
                : ['display_bank_transfer_instructions' => ['hosted_instructions_url' => 'https://pagos.stripe.test/spei']]],
        ];
        $this->avisar = function (string $tipo, array $objeto = ['id' => 'cs_1']) {
            $cuerpo = (string) json_encode(['type' => $tipo, 'data' => ['object' => $objeto]]);
            $firma = 't='.time().',v1='.hash_hmac('sha256', time().'.'.$cuerpo, 'whsec_x');

            return $this->call('POST', '/avisos-pago/posada/stripe', [], [], [], ['HTTP_Stripe-Signature' => $firma, 'CONTENT_TYPE' => 'application/json'], $cuerpo);
        };
        $this->sesionNueva = ['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/1'])];
    });

    it('sin nada activado, no manda nada de más', function (): void {
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        Http::assertSent(fn (PeticionHttp $peticion): bool => ! isset($peticion['payment_method_options']) && ! isset($peticion['customer']));
    });

    it('activado, pide OXXO con sus días y meses sin intereses', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 2, 'stripe_meses' => true]);
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect();

        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 2
            && $peticion['payment_method_options']['card']['installments']['enabled'] === 'true');
    });

    it('en otra moneda no lo pide', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_meses' => true, 'stripe_spei' => true]);
        $this->reserva->forceFill(['moneda' => 'USD'])->save();
        Http::fake($this->sesionNueva);

        ($this->abrir)()->assertRedirect();

        Http::assertNotSent(fn (PeticionHttp $peticion): bool => str_contains($peticion->url(), '/customers'));
        Http::assertSent(fn (PeticionHttp $peticion): bool => ! isset($peticion['payment_method_options']));
    });

    it('SPEI crea un cliente con su nombre y correo, una sola vez', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/customers' => Http::response(['id' => 'cus_1'])]);

        ($this->abrir)()->assertRedirect();

        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/customers') && $peticion['email'] === 'ana@example.com' && $peticion['name'] === 'Ana Ejemplo');
        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/checkout/sessions') && $peticion['customer'] === 'cus_1'
            && $peticion['payment_method_options']['customer_balance']['funding_type'] === 'bank_transfer'
            && $peticion['payment_method_options']['customer_balance']['bank_transfer']['type'] === 'mx_bank_transfer');
        expect($this->reserva->titular->refresh()->getAttribute('stripe_cliente'))->toBe('cus_1');

        // Otra sesión, mismo cliente.
        PagoEnLinea::query()->delete();
        ($this->abrir)()->assertRedirect();
        Http::assertSentCount(3);
    });

    it('si el cliente es de otra cuenta, crea otro y sigue', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        $this->reserva->titular->forceFill(['stripe_cliente' => 'cus_viejo'])->save();
        Http::fake([
            'api.stripe.com/v1/customers' => Http::response(['id' => 'cus_nuevo']),
            'api.stripe.com/v1/checkout/sessions' => Http::sequence()
                ->push(['error' => ['code' => 'resource_missing', 'param' => 'customer']], 400)
                ->push(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/1']),
        ]);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        expect($this->reserva->titular->refresh()->getAttribute('stripe_cliente'))->toBe('cus_nuevo');
    });

    it('si no se puede crear el cliente, todavía puede pagar con tarjeta', function (): void {
        ($this->opciones)(['stripe_spei' => true]);
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/customers' => Http::response(['error' => ['code' => 'x']], 500)]);

        ($this->abrir)()->assertRedirect('https://checkout.stripe.test/1');

        Http::assertSent(fn (PeticionHttp $peticion): bool => str_ends_with($peticion->url(), '/checkout/sessions') && ! isset($peticion['customer']));
    });

    it('al elegir OXXO no se le va la reserva a los 30 minutos, y ve su ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed')->assertOk();

        $cobro = PagoEnLinea::query()->sole();
        $this->reserva->refresh();
        expect($cobro->estado)->toBe('en_espera')
            ->and($this->reserva->pagado)->toBe(0)
            ->and($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($this->reserva->expira_en->greaterThan(now()->addDays(2)))->toBeTrue();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);

        $this->get($this->pagina)->assertOk()->assertSee('https://pagos.stripe.test/voucher')->assertSee(__('motor.espera.titulo'))->assertDontSee(__('motor.pagar_con.stripe'));

        // Volver a pulsar «Pagar» lleva a la misma ficha: dos a la vez se podrían pagar las dos.
        ($this->abrir)()->assertRedirect('https://pagos.stripe.test/voucher');
        expect(PagoEnLinea::query()->count())->toBe(1);
    });

    it('cuando paga en la tienda queda pagado, sin decir que fue con tarjeta', function (): void {
        $paso = 'unpaid';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => function () use (&$paso) {
            return Http::response(($this->sesionVoucher)($paso));
        }]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');
        $paso = 'paid';

        ($this->avisar)('checkout.session.async_payment_succeeded')->assertOk();

        $this->reserva->refresh();
        expect($this->reserva->pagado)->toBe(100000)
            ->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($this->reserva->expira_en)->toBeNull()
            ->and($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Otro)
            ->and(PagoEnLinea::query()->sole()->estado)->toBe('pagado');
        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'));
    });

    it('la transferencia se anota como transferencia', function (): void {
        $paso = 'unpaid';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => function () use (&$paso) {
            return Http::response(($this->sesionVoucher)($paso, 'customer_balance'));
        }]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        // Sin fecha de vencimiento en la ficha: tres días.
        $this->reserva->refresh();
        expect($this->reserva->expira_en->greaterThan(now()->addHours(71)))->toBeTrue()
            ->and($this->reserva->expira_en->lessThan(now()->addHours(73)))->toBeTrue();
        $this->get($this->pagina)->assertSee('https://pagos.stripe.test/spei');

        $paso = 'paid';
        ($this->avisar)('checkout.session.async_payment_succeeded');
        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Transferencia);
    });

    it('la tarjeta sigue siendo tarjeta, también a meses', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)('paid', 'card'))]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed')->assertOk();

        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Tarjeta);
    });

    it('si el vale vence, suelta las noches pronto y puede volver a intentarlo', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        ($this->avisar)('checkout.session.async_payment_failed')->assertOk();

        $this->reserva->refresh();
        expect(PagoEnLinea::query()->sole()->estado)->toBe('fallido')
            ->and($this->reserva->expira_en->lessThan(now()->addHour()))->toBeTrue();
        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'))->assertSee(__('motor.pagar_con.stripe'));
    });

    it('un vale vencido del que nunca llegó el aviso no sigue mandando a la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');

        $this->travel(4)->days();

        expect(app(App\Pagos\CobrosEnLinea::class)->enEspera($this->reserva->refresh()))->toBeNull();
    });

    it('si la retención venció mientras llenaba la ficha, la recupera', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        $this->travel(40)->minutes();
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada);

        ($this->avisar)('checkout.session.completed')->assertOk();

        $this->reserva->refresh();
        expect($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($this->reserva->expira_en)->not->toBeNull();
    });

    it('si Stripe no contesta al preguntar, usa lo que trae el aviso y no pierde el cobro', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response([], 500)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.async_payment_succeeded', ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 100000, 'currency' => 'mxn', 'payment_intent' => 'pi_1'])->assertOk();

        expect($this->reserva->refresh()->pagado)->toBe(100000);
    });

    it('un enlace de pago también espera, sin mezclarse con la reserva', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        $gestor = app(App\Pagos\GestorEnlacesPago::class);
        $enlace = $gestor->crear($this->reserva, 100000, 'Anticipo', 3, null);
        $url = $gestor->url($enlace);

        $this->post($url.'/stripe')->assertRedirect('https://checkout.stripe.test/1');
        ($this->avisar)('checkout.session.completed');

        $this->get($url)->assertSee('https://pagos.stripe.test/voucher');
        expect(app(App\Pagos\CobrosEnLinea::class)->enEspera($this->reserva->refresh()))->toBeNull();
    });

    it('una reserva sin retención no empieza a caducar por un enlace de pago', function (): void {
        $this->reserva->forceFill(['expira_en' => null])->save();
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        $gestor = app(App\Pagos\GestorEnlacesPago::class);
        $url = $gestor->url($gestor->crear($this->reserva, 100000, 'Anticipo', 3, null));
        $this->post($url.'/stripe')->assertRedirect();

        ($this->avisar)('checkout.session.completed');
        expect($this->reserva->refresh()->expira_en)->toBeNull();

        ($this->avisar)('checkout.session.async_payment_failed');
        expect($this->reserva->refresh()->expira_en)->toBeNull()
            ->and(PagoEnLinea::query()->sole()->estado)->toBe('fallido');
    });

    it('la ficha no dura más que lo que falta para llegar', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 7]);
        Http::fake($this->sesionNueva);

        ($this->abrir)();
        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 5);

        PagoEnLinea::query()->delete();
        $this->reserva->forceFill(['llegada' => $this->hoy->format('Y-m-d')])->save();
        ($this->abrir)();
        Http::assertSent(fn (PeticionHttp $peticion): bool => $peticion['payment_method_options']['oxxo']['expires_after_days'] === 1);
    });

    it('no aparta noches más allá del día de llegada', function (): void {
        $llegada = $this->hoy->addDay();
        $this->reserva->forceFill(['llegada' => $llegada->format('Y-m-d')])->save();
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)('unpaid', 'customer_balance'))]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');

        $limite = Illuminate\Support\Carbon::parse($llegada->format('Y-m-d'), 'America/Mexico_City')->endOfDay();
        expect($this->reserva->refresh()->expira_en->lessThanOrEqualTo($limite))->toBeTrue()
            ->and($this->reserva->expira_en->greaterThan(now()))->toBeTrue();
    });

    it('si ya no debe nada, no le enseña la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response(($this->sesionVoucher)())]);
        ($this->abrir)();
        ($this->avisar)('checkout.session.completed');
        $this->get($this->pagina)->assertSee(__('motor.espera.titulo'));

        app(GestorReservas::class)->registrarPago($this->reserva->refresh(), 100000, MetodoPago::Efectivo, $this->hoy);

        $this->get($this->pagina)->assertDontSee(__('motor.espera.titulo'));
    });

    it('un segundo aviso con menos datos no borra la ficha', function (): void {
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::sequence()->push(($this->sesionVoucher)())->push([], 500)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');
        ($this->avisar)('checkout.session.completed', ['id' => 'cs_1', 'status' => 'complete', 'payment_status' => 'unpaid', 'amount_total' => 100000, 'currency' => 'mxn', 'payment_intent' => 'pi_1']);

        expect(PagoEnLinea::query()->sole()->datos['instrucciones'])->toBe('https://pagos.stripe.test/voucher');
    });

    it('una dirección de ficha que no es https no llega a la página', function (): void {
        $sesion = ($this->sesionVoucher)();
        $sesion['payment_intent']['next_action']['oxxo_display_details']['hosted_voucher_url'] = 'javascript:alert(1)';
        Http::fake($this->sesionNueva + ['api.stripe.com/v1/checkout/sessions/*' => Http::response($sesion)]);
        ($this->abrir)();

        ($this->avisar)('checkout.session.completed');

        $this->get($this->pagina)->assertDontSee('javascript:alert', false)->assertSee(__('motor.espera.titulo'));
    });

    it('la pantalla del motor guarda las opciones, solo en pesos', function (): void {
        Livewire::actingAs($this->admin)->test(MotorDeReservas::class)
            ->set('datos.stripe_oxxo', true)
            ->set('datos.stripe_oxxo_dias', 4)
            ->set('datos.stripe_meses', true)
            ->set('datos.stripe_spei', true)
            ->call('guardar')
            ->assertHasNoErrors();

        Ajustes::olvidar();
        expect((new AjustesMotor($this->propiedad->refresh()))->opcionesStripe())->toBe(['oxxo' => true, 'oxxo_dias' => 4, 'meses' => true, 'spei' => true]);
    });

    it('los días se quedan entre 1 y 7 aunque alguien guarde otra cosa', function (): void {
        ($this->opciones)(['stripe_oxxo' => true, 'stripe_oxxo_dias' => 99]);

        expect((new AjustesMotor($this->propiedad->refresh()))->opcionesStripe()['oxxo_dias'])->toBe(7);
    });

    it('anonimizar olvida su cliente de Stripe', function (): void {
        $huesped = $this->reserva->titular;
        $huesped->forceFill(['stripe_cliente' => 'cus_1'])->save();

        app(App\Privacidad\GestorPrivacidad::class)->anonimizar($huesped->refresh());

        expect($huesped->refresh()->getAttribute('stripe_cliente'))->toBeNull();
    });
});

describe('Mercado Pago en México', function (): void {
    beforeEach(function (): void {
        $ajustes = new AjustesMotor($this->propiedad);
        $ajustes->guardarLlave('mercadopago_token', 'TEST-token');
        $this->post('/reservar/posada', ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1'])->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->pagina = '/reservar/posada/r/'.$this->reserva->codigo_publico;
        $this->respuestaDe = fn (array $pago): array => $pago + ['id' => 555, 'transaction_amount' => 1000.0, 'currency_id' => 'MXN', 'external_reference' => App\Pagos\CobrosEnLinea::referenciaInterna(PagoEnLinea::query()->sole()->id)];
        $this->pago = ['status' => 'pending', 'status_detail' => 'pending_waiting_payment', 'payment_type_id' => 'ticket', 'date_of_expiration' => now()->addDays(3)->toIso8601String(), 'transaction_details' => ['external_resource_url' => 'https://www.mercadopago.test/ficha']];
        Http::fake([
            'api.mercadopago.com/checkout/preferences' => Http::response(['id' => 'pref_1', 'init_point' => 'https://mp.test/pagar']),
            'api.mercadopago.com/v1/payments/555' => function () {
                return Http::response(($this->respuestaDe)($this->pago));
            },
        ]);
        $this->post($this->pagina.'/pagar/mercadopago')->assertRedirect('https://mp.test/pagar');
        $this->avisar = fn () => $this->postJson('/avisos-pago/posada/mercadopago', ['type' => 'payment', 'data' => ['id' => '555']]);
    });

    it('la ficha de OXXO aparta las noches y se enseña', function (): void {
        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('en_espera')
            ->and($this->reserva->refresh()->expira_en->greaterThan(now()->addDays(2)))->toBeTrue();
        $this->get($this->pagina)->assertSee('https://www.mercadopago.test/ficha');
    });

    it('pagada, se anota como lo que fue', function (): void {
        ($this->avisar)();
        $this->pago = ['status' => 'approved', 'status_detail' => 'accredited', 'payment_type_id' => 'ticket'];

        ($this->avisar)()->assertOk();

        expect($this->reserva->refresh()->pagado)->toBe(100000)
            ->and($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Otro);
    });

    it('cancelada por vencer, suelta las noches pronto', function (): void {
        ($this->avisar)();
        $this->pago = ['status' => 'cancelled', 'status_detail' => 'expired', 'payment_type_id' => 'ticket'];

        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('fallido')
            ->and($this->reserva->refresh()->expira_en->lessThan(now()->addHour()))->toBeTrue();
    });

    it('una tarjeta en revisión no es una ficha: no aparta de más', function (): void {
        $antes = $this->reserva->expira_en;
        $this->pago = ['status' => 'in_process', 'status_detail' => 'pending_review_manual', 'payment_type_id' => 'credit_card'];

        ($this->avisar)()->assertOk();

        expect(PagoEnLinea::query()->sole()->estado)->toBe('pendiente')
            ->and($this->reserva->refresh()->expira_en->equalTo($antes))->toBeTrue();
    });

    it('sin método conocido se queda como tarjeta, como hasta ahora; con tarjeta y transferencia, lo que fueron', function (): void {
        $this->pago = ['status' => 'approved', 'payment_type_id' => 'bank_transfer'];

        ($this->avisar)()->assertOk();

        expect($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Transferencia);
    });
});
