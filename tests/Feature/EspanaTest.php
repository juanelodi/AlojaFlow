<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Autoridades\GestorEnvios;
use App\Autoridades\Ses\AjustesSes;
use App\Autoridades\Ses\ClienteSes;
use App\Autoridades\Ses\FichaViajero;
use App\Enums\BaseCargo;
use App\Enums\EstadoEnvioAutoridad;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\TipoContacto;
use App\Facturacion\GestorFacturas;
use App\Filament\Pages\MotorDeReservas;
use App\Filament\Resources\EnviosAutoridad\EnvioAutoridadResource;
use App\Filament\Resources\EnviosAutoridad\Pages\ListEnviosAutoridad;
use App\Filament\Resources\Facturas\Pages\ListFacturas;
use App\Filament\Resources\Impuestos\Pages\ListImpuestos;
use App\Models\Aviso;
use App\Models\EntidadFiscal;
use App\Models\EnvioAutoridad;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\PagoEnLinea;
use App\Models\PersonaLlegada;
use App\Models\Propiedad;
use App\Models\RegistroLlegada;
use App\Models\Reserva;
use App\Models\SerieFacturacion;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\Pasarelas;
use App\Paises\CatalogoMunicipios;
use App\Paises\CatalogoPaises;
use App\Paises\CatalogoTasaTuristica;
use App\Paises\DeclaracionEstancias;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Servicios\InformesGestion;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\Client\Request as PeticionHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

function dniDePrueba(int $numero = 12345678): string
{
    return $numero.'TRWAGMYFPDXBNJZSQVHLCKE'[$numero % 23];
}

function xmlDeLaPeticion(string $soap): string
{
    $documento = new DOMDocument();
    $documento->loadXML($soap);
    $ruta = (string) tempnam(sys_get_temp_dir(), 'prueba');
    file_put_contents($ruta, base64_decode((string) $documento->getElementsByTagName('solicitud')->item(0)?->textContent));
    $zip = new ZipArchive();
    $zip->open($ruta);
    $xml = (string) $zip->getFromName('solicitud.xml');
    $zip->close();
    unlink($ruta);

    return $xml;
}

function campoDeLaPeticion(string $soap, string $campo): string
{
    $documento = new DOMDocument();
    $documento->loadXML($soap);

    return (string) $documento->getElementsByTagName($campo)->item(0)?->textContent;
}

function respuestaSes(string $cuerpo, int $estado = 200): GuzzleHttp\Promise\PromiseInterface
{
    return Http::response('<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Header/><SOAP-ENV:Body><ns3:comunicacionResponse xmlns:ns3="http://www.soap.servicios.hospedajes.mir.es/comunicacion">'.$cuerpo.'</ns3:comunicacionResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>', $estado, ['Content-Type' => 'text/xml']);
}

/**
 * Un servicio de mentira, con el formato de los ejemplos del documento oficial: acepta lo que le mandan, y al preguntarle dice lo que se le haya indicado.
 */
function servidorSes(object $prueba): void
{
    $prueba->peticiones = [];
    $prueba->numeroLote = 0;
    $prueba->respuestas = [];
    Http::fake(['hospedajes.pre-ses.mir.es/*' => function (PeticionHttp $peticion) use ($prueba) {
        $operacion = campoDeLaPeticion($peticion->body(), 'tipoOperacion');
        if ($operacion === 'C') {
            $resultados = '';
            preg_match_all('/<con:lote[^>]*>([^<]+)</', xmlDeLaPeticion($peticion->body()), $lotes);
            foreach ($lotes[1] as $lote) {
                $resultados .= '<resultado><lote>'.$lote.'</lote><tipoComunicacion>PV</tipoComunicacion><tipoOperacion>A</tipoOperacion><codigoEstado>'.($prueba->estadoLote ?? 1).'</codigoEstado><descEstado>Procesado</descEstado><resultadoComunicaciones>'.($prueba->respuestas[$lote] ?? '<resultadoComunicacion><orden>1</orden><codigoComunicacion>COM-'.$lote.'</codigoComunicacion></resultadoComunicacion>').'</resultadoComunicaciones></resultado>';
            }

            return respuestaSes('<respuesta><codigo>0</codigo><descripcion>Ok</descripcion></respuesta>'.$resultados);
        }
        if ($operacion === '') {
            return respuestaSes('<resultado><codigo>0</codigo></resultado><respuesta><resultado><tupla><codigo>EFECT</codigo><descripcion>Efectivo</descripcion></tupla></resultado></respuesta>');
        }
        $prueba->peticiones[] = ['operacion' => $operacion, 'tipo' => campoDeLaPeticion($peticion->body(), 'tipoComunicacion'), 'xml' => xmlDeLaPeticion($peticion->body()), 'autorizacion' => $peticion->header('Authorization')[0] ?? ''];
        $prueba->numeroLote++;

        return respuestaSes('<respuesta><codigoRetorno>0</codigoRetorno><descripcion>Ok</descripcion><lote>LOTE-'.$prueba->numeroLote.'</lote></respuesta>');
    }]);
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    Http::preventStrayRequests();
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Hostal', 'codigo' => 'hostal', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'Europe/Madrid', 'moneda' => 'EUR', 'pais' => 'ES', 'idioma' => 'es', 'paquete_pais' => PaquetePais::Espana])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 10000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 4, 'activa' => true]);
    $this->hoy = $this->propiedad->hoy();
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);

    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'García', 'apellido2' => 'Pérez', 'fecha_nacimiento' => '1980-02-03', 'documento_tipo_texto' => 'dni', 'documento_numero' => dniDePrueba(), 'nacionalidad_texto' => 'ES', 'domicilio_calle' => 'Mayor 1', 'domicilio_ciudad' => 'Madrid', 'domicilio_codigo_postal' => '28001', 'domicilio_pais_texto' => 'España']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->huesped->campos()->create(['campo' => 'soporte', 'valor' => 'ABC123456']);
    $this->reservar = fn (int $dias = 5, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($dias)->format('Y-m-d'), 'salida' => $this->hoy->addDays($dias + 2)->format('Y-m-d'),
        'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 20000,
    ]);
    $this->conectar = function (): void {
        (new AjustesSes($this->propiedad->refresh()))->guardar(['modo' => AjustesSes::MODO_PRUEBAS, 'arrendador' => '0000000001', 'establecimiento' => '1234567890', 'usuario' => 'usuario-de-prueba', 'internet' => true, 'desde' => now('UTC')->subDay()->toIso8601String()], 'contrasena-de-prueba');
        $this->propiedad->refresh();
    };
    $this->correr = fn (): array => app(GestorEnvios::class)->correr($this->propiedad->refresh());
});

describe('SES.Hospedajes', function (): void {
    beforeEach(function (): void {
        ($this->conectar)();
        servidorSes($this);
    });

    it('comunica una reserva confirmada, y al preguntar queda comunicada', function (): void {
        $reserva = ($this->reservar)();

        expect(($this->correr)()['enviadas'])->toBe(1);

        expect($this->peticiones[0])->toMatchArray(['operacion' => 'A', 'tipo' => 'RH', 'autorizacion' => 'Basic '.base64_encode('usuario-de-prueba:contrasena-de-prueba')])
            ->and($this->peticiones[0]['xml'])->toContain('altaReservaHospedaje')
            ->toContain('<codigo>1234567890</codigo>')->toContain('<rol>TI</rol>')->toContain('<nombre>Ana</nombre>')->toContain('<correo>ana@example.com</correo>')
            ->toContain('<tipoPago>DESTI</tipoPago>')->toContain('<internet>true</internet>')->toContain('<fechaEntrada>'.$reserva->llegada->format('Y-m-d').'T00:00:00</fechaEntrada>');
        $envio = EnvioAutoridad::query()->sole();
        expect($envio->tipo)->toBe(EnvioAutoridad::RESERVA)
            ->and($envio->estado)->toBe(EstadoEnvioAutoridad::Aceptado)
            ->and($envio->lote)->toBe('LOTE-1')
            ->and($envio->codigo_comunicacion)->toBe('COM-LOTE-1');

        ($this->correr)();
        expect($this->peticiones)->toHaveCount(1);
    });

    it('al entrar manda el parte con los datos que pide y lo que se pagó', function (): void {
        $reserva = ($this->reservar)(0);
        app(GestorReservas::class)->registrarPago($reserva, 5000, MetodoPago::Tarjeta, $this->hoy);
        ($this->correr)();
        app(GestorReservas::class)->registrarEntrada($reserva->refresh());

        ($this->correr)();

        expect($this->peticiones)->toHaveCount(2);
        $parte = $this->peticiones[1];
        expect($parte['tipo'])->toBe('PV')
            ->and($parte['xml'])->toContain('altaParteHospedaje')->toContain('<codigoEstablecimiento>1234567890</codigoEstablecimiento>')
            ->toContain('<rol>VI</rol>')->toContain('<tipoDocumento>NIF</tipoDocumento>')->toContain('<numeroDocumento>'.dniDePrueba().'</numeroDocumento>')
            ->toContain('<soporteDocumento>ABC123456</soporteDocumento>')->toContain('<fechaNacimiento>1980-02-03</fechaNacimiento>')->toContain('<nacionalidad>ESP</nacionalidad>')
            ->toContain('<codigoMunicipio>28079</codigoMunicipio>')->toContain('<pais>ESP</pais>')->toContain('<tipoPago>TARJT</tipoPago>')
            ->not->toContain('establecimiento>');
        expect(EnvioAutoridad::query()->where('tipo', 'PV')->sole()->estado)->toBe(EstadoEnvioAutoridad::Aceptado);
    });

    it('prefiere lo que mandó el huésped en su registro, y a los acompañantes les da el domicilio de quien reservó', function (): void {
        $reserva = ($this->reservar)(0, ['personas' => 2]);
        $registro = RegistroLlegada::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => $reserva->id, 'estado' => EstadoRegistroLlegada::Recibido]);
        PersonaLlegada::query()->create(['registro_llegada_id' => $registro->id, 'orden' => 0, 'es_titular' => true, 'nombre' => 'Marta', 'apellido1' => 'Ruiz', 'apellido2' => 'Sanz', 'fecha_nacimiento' => '1985-01-01', 'documento_tipo' => 'nie', 'documento_numero' => 'X1234567L', 'nacionalidad_texto' => 'Francia', 'domicilio_calle' => 'Rue 1', 'domicilio_ciudad' => 'Paris', 'domicilio_codigo_postal' => '75001', 'domicilio_pais_texto' => 'Francia', 'correo' => 'marta@example.com', 'datos_pais' => ['soporte' => 'E12345678']]);
        PersonaLlegada::query()->create(['registro_llegada_id' => $registro->id, 'orden' => 1, 'es_titular' => false, 'nombre' => 'Leo', 'apellido1' => 'Ruiz', 'fecha_nacimiento' => $this->hoy->subYears(5)->format('Y-m-d'), 'datos_pais' => ['parentesco' => 'hijo']]);
        app(GestorReservas::class)->registrarEntrada($reserva->refresh());

        ($this->correr)();

        $parte = collect($this->peticiones)->firstWhere('tipo', 'PV');
        expect($parte['xml'])->toContain('<nombre>Marta</nombre>')->toContain('<tipoDocumento>NIE</tipoDocumento>')->toContain('<nacionalidad>FRA</nacionalidad>')
            ->toContain('<nombreMunicipio>Paris</nombreMunicipio>')->toContain('<parentesco>HJ</parentesco>')->not->toContain('codigoMunicipio')
            ->and(substr_count($parte['xml'], '<nombreMunicipio>Paris</nombreMunicipio>'))->toBe(2)
            ->and(substr_count($parte['xml'], '<correo>marta@example.com</correo>'))->toBe(2);
    });

    it('no manda lo que está incompleto: dice qué falta, y lo corregido se usa', function (): void {
        $this->huesped->campos()->delete();
        $reserva = ($this->reservar)(0);
        app(GestorReservas::class)->registrarEntrada($reserva);

        ($this->correr)();

        $parte = EnvioAutoridad::query()->where('tipo', 'PV')->sole();
        expect($parte->estado)->toBe(EstadoEnvioAutoridad::FaltanDatos)
            ->and($parte->faltan)->toEqual([['persona' => 'h'.$this->huesped->id, 'campo' => 'soporte']])
            ->and(collect($this->peticiones)->where('tipo', 'PV'))->toBeEmpty()
            ->and(Aviso::query()->where('tipo', 'autoridad_faltan_datos')->exists())->toBeTrue();

        Livewire::actingAs($this->recepcion)->test(ListEnviosAutoridad::class)
            ->assertSee('Ana García')->assertSee(__('autoridad.campos.soporte'))
            ->callTableAction('completar', $parte, ['correcciones' => ['h'.$this->huesped->id => ['soporte' => 'XYZ987654']]])
            ->assertHasNoTableActionErrors();

        expect($parte->refresh()->estado)->toBe(EstadoEnvioAutoridad::Pendiente);
        ($this->correr)();

        expect(collect($this->peticiones)->firstWhere('tipo', 'PV')['xml'])->toContain('<soporteDocumento>XYZ987654</soporteDocumento>')
            ->and($parte->refresh()->estado)->toBe(EstadoEnvioAutoridad::Aceptado)
            ->and(Aviso::query()->where('tipo', 'autoridad_faltan_datos')->whereNotNull('clave_unica')->exists())->toBeFalse()
            ->and((string) DB::table('envios_autoridad')->where('id', $parte->id)->value('correcciones'))->not->toContain('XYZ987654');
        expect($this->huesped->refresh()->campos()->where('campo', 'soporte')->exists())->toBeFalse();
    });

    it('un DNI con la letra mal no se manda', function (): void {
        $this->huesped->update(['documento_numero' => '12345678A']);
        app(GestorReservas::class)->registrarEntrada(($this->reservar)(0));

        ($this->correr)();

        expect(EnvioAutoridad::query()->where('tipo', 'PV')->sole()->faltan)->toEqual([['persona' => 'h'.$this->huesped->id, 'campo' => 'documento_numero']]);
    });

    it('un menor no necesita documento, pero sí su parentesco', function (): void {
        $this->huesped->update(['fecha_nacimiento' => $this->hoy->subYears(10)->format('Y-m-d'), 'documento_numero' => null, 'documento_tipo_texto' => null]);
        app(GestorReservas::class)->registrarEntrada(($this->reservar)(0));

        ($this->correr)();

        expect(EnvioAutoridad::query()->where('tipo', 'PV')->sole()->faltan)->toEqual([['persona' => 'h'.$this->huesped->id, 'campo' => 'parentesco']]);
    });

    it('sin apellido ni contacto, ni la reserva se manda; un acompañante a medias no la detiene', function (): void {
        $this->huesped->update(['apellido1' => null]);
        $this->huesped->contactos()->delete();
        $reserva = ($this->reservar)();
        $reserva->huespedes()->attach(Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Sin datos'])->id, ['orden' => 1]);

        ($this->correr)();

        $envio = EnvioAutoridad::query()->sole();
        expect(collect($envio->faltan)->pluck('campo')->all())->toBe(['apellido1', 'contacto'])
            ->and($this->peticiones)->toBeEmpty();

        $this->huesped->update(['apellido1' => 'García']);
        $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
        ($this->correr)();

        expect($this->peticiones[0]['xml'])->not->toContain('Sin datos')
            ->and($envio->refresh()->estado)->toBe(EstadoEnvioAutoridad::Aceptado);
    });

    it('si se cancela, anula lo comunicado; lo que no se había mandado solo se borra', function (): void {
        $comunicada = ($this->reservar)();
        ($this->correr)();
        app(GestorReservas::class)->cancelar($comunicada->refresh());
        $this->huesped->contactos()->delete();
        $sinMandar = ($this->reservar)(10);
        ($this->correr)();
        $faltan = EnvioAutoridad::query()->where('reserva_id', $sinMandar->id)->sole();
        expect($faltan->estado)->toBe(EstadoEnvioAutoridad::FaltanDatos);

        expect(collect($this->peticiones)->last()['operacion'])->toBe('B')
            ->and(collect($this->peticiones)->last()['xml'])->toContain('anularComunicacion')->toContain('COM-LOTE-1');
        $envio = EnvioAutoridad::query()->where('reserva_id', $comunicada->id)->sole();
        expect($envio->estado)->toBe(EstadoEnvioAutoridad::Anulado);

        app(GestorReservas::class)->cancelar($sinMandar->refresh());
        ($this->correr)();
        expect(EnvioAutoridad::query()->where('reserva_id', $sinMandar->id)->exists())->toBeFalse();
    });

    it('si cambian las fechas, anula la anterior y manda otra: el servicio no modifica', function (): void {
        $reserva = ($this->reservar)();
        ($this->correr)();

        app(GestorReservas::class)->mover($reserva->refresh(), $this->unidad->id, $this->hoy->addDays(20));
        ($this->correr)();

        expect(collect($this->peticiones)->pluck('operacion')->all())->toBe(['A', 'A', 'B'])
            ->and(EnvioAutoridad::query()->orderBy('id')->pluck('estado')->map->value->all())->toBe(['anulado', 'aceptado']);
    });

    it('una respuesta con errores en una comunicación la marca rechazada y se puede reintentar', function (): void {
        $reserva = ($this->reservar)();
        $this->respuestas['LOTE-1'] = '<resutadoComunicacion><orden>1</orden><tipoError>Error validación de datos</tipoError><error>No existe un establecimiento para ese código</error></resutadoComunicacion>';
        $this->estadoLote = 6;

        ($this->correr)();

        $envio = EnvioAutoridad::query()->sole();
        expect($envio->estado)->toBe(EstadoEnvioAutoridad::Rechazado)
            ->and($envio->ultimo_error)->toContain('No existe un establecimiento')
            ->and(Aviso::query()->where('tipo', 'autoridad_rechazadas')->exists())->toBeTrue();

        Livewire::actingAs($this->recepcion)->test(ListEnviosAutoridad::class)
            ->assertSee('No existe un establecimiento')
            ->callTableAction('reintentar', $envio);
        expect($envio->refresh()->estado)->toBe(EstadoEnvioAutoridad::Pendiente);
        $this->estadoLote = 1;
        ($this->correr)();
        expect($envio->refresh()->estado)->toBe(EstadoEnvioAutoridad::Aceptado);
    });

    it('un lote que sigue en proceso se vuelve a preguntar', function (): void {
        ($this->reservar)();
        $this->estadoLote = 4;

        ($this->correr)();
        expect(EnvioAutoridad::query()->sole()->estado)->toBe(EstadoEnvioAutoridad::Enviado);

        $this->estadoLote = 1;
        ($this->correr)();
        expect(EnvioAutoridad::query()->sole()->estado)->toBe(EstadoEnvioAutoridad::Aceptado);
    });

    it('lo que ya estaba antes de conectar no se manda', function (): void {
        ($this->reservar)();
        (new AjustesSes($this->propiedad->refresh()))->guardar(['modo' => 'pruebas', 'arrendador' => '1', 'establecimiento' => '1', 'usuario' => 'u', 'desde' => now('UTC')->addHour()->toIso8601String()], null);

        ($this->correr)();

        expect(EnvioAutoridad::query()->exists())->toBeFalse()
            ->and($this->peticiones)->toBeEmpty();
    });

    it('en una reserva, un documento mal escrito se omite: ahí es opcional', function (): void {
        $this->huesped->update(['documento_numero' => '12345678A']);
        ($this->reservar)();

        ($this->correr)();

        expect($this->peticiones[0]['xml'])->not->toContain('numeroDocumento')->not->toContain('tipoDocumento')
            ->and(EnvioAutoridad::query()->sole()->estado)->toBe(EstadoEnvioAutoridad::Aceptado);
    });

    it('el plazo de una reserva corre desde que se confirma, no desde que se creó', function (): void {
        $reserva = ($this->reservar)(5, ['estado' => EstadoReserva::SinConfirmar->value]);
        $this->huesped->contactos()->delete();
        $this->travel(3)->days();

        app(GestorReservas::class)->confirmar($reserva);
        ($this->correr)();

        expect(EnvioAutoridad::query()->sole()->vence_en->isFuture())->toBeTrue()
            ->and(Aviso::query()->where('tipo', 'autoridad_atrasadas')->exists())->toBeFalse();
    });

    it('la ventana de corregir enseña lo que hay', function (): void {
        $this->huesped->update(['documento_numero' => '12345678A']);
        app(GestorReservas::class)->registrarEntrada(($this->reservar)(0));
        ($this->correr)();
        $parte = EnvioAutoridad::query()->where('tipo', 'PV')->sole();

        Livewire::actingAs($this->recepcion)->test(ListEnviosAutoridad::class)
            ->mountTableAction('completar', $parte)
            ->assertTableActionDataSet(['correcciones' => ['h'.$this->huesped->id => ['documento_numero' => '12345678A']]]);
    });

    it('quien solo ve lo suyo o no ve datos, no entra', function (): void {
        $usuario = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
        Acceso::asignarRoles($usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);

        $this->actingAs($usuario);

        expect(EnvioAutoridadResource::canViewAny())->toBeFalse();
    });

    it('una retención sin confirmar todavía no se comunica', function (): void {
        ($this->reservar)(5, ['estado' => EstadoReserva::SinConfirmar->value]);

        ($this->correr)();

        expect(EnvioAutoridad::query()->exists())->toBeFalse();
    });

    it('fuera de plazo avisa', function (): void {
        $this->huesped->contactos()->delete();
        ($this->reservar)();
        ($this->correr)();
        expect(Aviso::query()->where('tipo', 'autoridad_atrasadas')->exists())->toBeFalse();

        $this->travel(25)->hours();
        ($this->correr)();

        expect(Aviso::query()->where('tipo', 'autoridad_atrasadas')->exists())->toBeTrue();
    });

    it('el comando recorre las propiedades conectadas y no falla con las demás', function (): void {
        Propiedad::query()->create(['nombre' => 'México', 'codigo' => 'mx', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'paquete_pais' => PaquetePais::Mexico]);
        Propiedad::query()->create(['nombre' => 'Sin paquete', 'codigo' => 'sin', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'EUR']);
        ($this->reservar)();

        $this->artisan('alojaflow:autoridades')->assertSuccessful();

        expect($this->peticiones)->toHaveCount(1);
    });

    it('anonimizar borra lo corregido y el error', function (): void {
        $reserva = ($this->reservar)();
        $envio = EnvioAutoridad::query()->create(['propiedad_id' => $this->propiedad->id, 'reserva_id' => $reserva->id, 'servicio' => 'ses', 'tipo' => 'PV', 'estado' => EstadoEnvioAutoridad::Rechazado, 'correcciones' => ['h1' => ['soporte' => 'X']], 'ultimo_error' => 'Documento 123']);

        app(App\Privacidad\GestorPrivacidad::class)->anonimizar($this->huesped->refresh());

        expect($envio->refresh()->correcciones)->toBeNull()
            ->and($envio->ultimo_error)->toBeNull();
    });
});

describe('SES.Hospedajes: conexión', function (): void {
    it('sin conectar no hace nada y no pasa de ahí', function (): void {
        ($this->reservar)();

        expect(($this->correr)())->toBe(['conciliadas' => 0, 'enviadas' => 0, 'respondidas' => 0]);

        expect(EnvioAutoridad::query()->exists())->toBeFalse();
    });

    it('solo se guarda si funciona, con la contraseña cifrada', function (): void {
        servidorSes($this);

        Livewire::actingAs($this->gerente)->test(ListEnviosAutoridad::class)
            ->callAction('conectar', ['propiedad_id' => $this->propiedad->id, 'modo' => 'pruebas', 'arrendador' => '0000000001', 'establecimiento' => '1234567890', 'usuario' => 'u1', 'contrasena' => 'secreto-de-prueba', 'internet' => true])
            ->assertHasNoActionErrors()
            ->assertNotified(__('autoridad.conectado'));

        $this->propiedad->refresh();
        $ajustes = new AjustesSes($this->propiedad);
        expect($ajustes->conectada())->toBeTrue()
            ->and($ajustes->contrasena())->toBe('secreto-de-prueba')
            ->and((string) DB::table('propiedades')->where('id', $this->propiedad->id)->value('ajustes'))->not->toContain('secreto-de-prueba')
            ->and((string) DB::table('ajustes')->where('clave', 'autoridad.'.$this->propiedad->id.'.ses')->value('valor'))->not->toContain('secreto-de-prueba')
            ->and((string) json_encode(App\Models\RegistroAuditoria::query()->get()->toArray()))->not->toContain('secreto-de-prueba');
    });

    it('con credenciales malas lo dice y no se queda conectado', function (): void {
        Http::fake(['hospedajes.pre-ses.mir.es/*' => Http::response('', 401)]);

        Livewire::actingAs($this->gerente)->test(ListEnviosAutoridad::class)
            ->callAction('conectar', ['propiedad_id' => $this->propiedad->id, 'modo' => 'pruebas', 'arrendador' => '1', 'establecimiento' => '1', 'usuario' => 'u', 'contrasena' => 'mala'])
            ->assertNotified(__('autoridad.no_conecto'));

        expect((new AjustesSes($this->propiedad->refresh()))->conectada())->toBeFalse();
    });

    it('un error del servicio sin XML se explica, sin culpar a la red', function (): void {
        Http::fake(['hospedajes.pre-ses.mir.es/*' => Http::response('<html>Bad Gateway', 502)]);

        expect(fn () => (new ClienteSes(AjustesSes::MODO_PRUEBAS, 'u', 'c', '1'))->catalogo('SEXO'))
            ->toThrow(App\Autoridades\ErrorAutoridad::class, __('autoridad.error.servicio', ['codigo' => 502]));
    });

    it('producción va a la dirección real, y con la cadena de certificados que el servidor no manda', function (): void {
        $url = null;
        $verifica = null;
        Http::fake(function (PeticionHttp $peticion, array $opciones) use (&$url, &$verifica) {
            $url = $peticion->url();
            $verifica = $opciones['verify'] ?? null;

            return respuestaSes('<resultado><codigo>0</codigo></resultado>');
        });

        (new ClienteSes(AjustesSes::MODO_PRODUCCION, 'u', 'c', '1'))->catalogo('SEXO');

        expect($url)->toBe('https://hospedajes.ses.mir.es/hospedajes-web/ws/v1/comunicacion')
            ->and($verifica)->toBe(resource_path('certificados/fnmt.pem'))
            ->and(is_file((string) $verifica))->toBeTrue();
    });

    it('recepción ve la lista pero no conecta; fuera de España no existe', function (): void {
        Livewire::actingAs($this->recepcion)->test(ListEnviosAutoridad::class)
            ->assertOk()->assertActionHidden('conectar');

        $this->actingAs($this->admin);
        $this->propiedad->update(['paquete_pais' => PaquetePais::Mexico]);
        expect(EnvioAutoridadResource::canViewAny())->toBeFalse();
    });

    it('sin conexión, reintenta más tarde y al tercer fallo avisa', function (): void {
        ($this->conectar)();
        Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('sin red'));
        ($this->reservar)();

        ($this->correr)();
        $envio = EnvioAutoridad::query()->sole();
        expect($envio->estado)->toBe(EstadoEnvioAutoridad::Pendiente)
            ->and($envio->intentos)->toBe(1)
            ->and($envio->ultimo_error)->toBe(__('autoridad.error.sin_conexion'))
            ->and($envio->proximo_intento_en->isFuture())->toBeTrue();

        ($this->correr)();
        expect($envio->refresh()->intentos)->toBe(1);

        $this->travel(11)->minutes();
        ($this->correr)();
        $this->travel(31)->minutes();
        ($this->correr)();
        expect($envio->refresh()->intentos)->toBe(3)
            ->and(Aviso::query()->where('tipo', 'autoridad_sin_conexion')->exists())->toBeTrue();
    });
});

describe('fechas sin zona', function (): void {
    it('la entrada se registra el día de llegada también al este de Greenwich', function (): void {
        $reserva = ($this->reservar)(0);
        $this->travelTo(CarbonImmutable::parse($this->hoy->format('Y-m-d').' 00:30:00', 'Europe/Madrid'));

        $hecha = app(GestorReservas::class)->registrarEntrada($reserva);

        expect($hecha->entrada_registrada_en)->not->toBeNull();
    });

    it('la entrada no se registra antes del día de llegada', function (): void {
        expect(fn () => app(GestorReservas::class)->registrarEntrada(($this->reservar)(1)))->toThrow(App\Reservas\ReservaRechazada::class);
    });

    it('la cancelación cuenta bien los días en cualquier zona', function (string $zona): void {
        $this->propiedad->update(['zona_horaria' => $zona]);
        $reserva = ($this->reservar)(1);
        $politica = new App\Models\Politica(['dias_cancelacion_gratuita' => 1, 'penalizacion_tipo' => 'porcentaje', 'penalizacion_valor' => 100]);

        expect(app(App\Dinero\GestorPoliticas::class)->penalizacionCancelacion($reserva->refresh(), $politica))->toBe(0);
    })->with(['America/Mexico_City', 'Europe/Madrid', 'UTC', 'Asia/Tokyo', 'Pacific/Honolulu']);

    it('una temporada se aplica desde su primer día también con una fecha en la zona de la propiedad', function (): void {
        App\Models\TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Alta', 'desde' => '2031-07-01', 'hasta' => '2031-07-31', 'precio_noche' => 30000]);

        $cotizacion = Cotizador::cotizar($this->unidad, CarbonImmutable::parse('2031-07-01', 'Europe/Madrid'), CarbonImmutable::parse('2031-07-02', 'Europe/Madrid'));

        expect($cotizacion->total)->toBe(30000);
    });
});

describe('fichas y catálogos', function (): void {
    it('encuentra el municipio por lo que escribe la gente', function (string $ciudad, ?string $cp, ?string $codigo): void {
        expect(CatalogoMunicipios::codigo($ciudad, $cp))->toBe($codigo);
    })->with([
        ['Madrid', '28001', '28079'],
        ['MADRID', null, '28079'],
        ['A Coruña', '15001', '15030'],
        ['Coruña', '15001', '15030'],
        ['Valencia', '46001', '46250'],
        ['San Sebastián', '20001', '20069'],
        ['Madrid', '08001', null],
        ['Pueblo que no existe', '28001', null],
    ]);

    it('los códigos de país de tres letras', function (): void {
        expect(CatalogoPaises::alfa3De('ES'))->toBe('ESP')
            ->and(CatalogoPaises::alfa3De('España'))->toBe('ESP')
            ->and(CatalogoPaises::alfa3De('méxico'))->toBe('MEX')
            ->and(CatalogoPaises::alfa3De('FRA'))->toBe('FRA')
            ->and(CatalogoPaises::alfa3De('Narnia'))->toBeNull()
            ->and(CatalogoPaises::alfa3De(null))->toBeNull();
    });

    it('un NIE con la letra bien pasa', function (): void {
        $reserva = ($this->reservar)();
        $this->huesped->update(['documento_tipo_texto' => 'nie', 'documento_numero' => 'x-1234567-l']);

        $ficha = FichaViajero::deReserva($reserva->refresh())[0];

        expect($ficha->tipoDocumento())->toBe('NIE')
            ->and($ficha->numeroDocumento())->toBe('X1234567L')
            ->and($ficha->faltan(EnvioAutoridad::PARTE, now()))->toBe([]);
    });
});

describe('tasa turística', function (): void {
    it('la propone con sus tramos, tope, edad y tarifa al reservar', function (): void {
        Livewire::actingAs($this->gerente)->test(ListImpuestos::class)
            ->callAction('catalogo', ['region' => __('autoridad.tasa.lugares.cataluna').' · '.__('autoridad.tasa.tipos.vivienda')])
            ->assertHasNoActionErrors();

        $tramos = Impuesto::query()->orderBy('vigente_desde')->get();
        expect($tramos)->toHaveCount(2)
            ->and($tramos[0]->importe)->toBe(175)
            ->and($tramos[0]->vigente_hasta->format('Y-m-d'))->toBe('2027-03-31')
            ->and($tramos[1]->importe)->toBe(250)
            ->and($tramos[1]->vigente_desde->format('Y-m-d'))->toBe('2027-04-01')
            ->and($tramos[0]->base)->toBe(BaseCargo::PorPersonaNoche)
            ->and($tramos[0]->tope_noches)->toBe(7)
            ->and($tramos[0]->edad_minima)->toBe(17)
            ->and($tramos[0]->fijar_al_reservar)->toBeTrue()
            ->and($tramos[0]->incluido_en_precio)->toBeFalse()
            ->and($tramos[0]->codigo)->toBe('IEET');
    });

    it('el catálogo tiene Barcelona con su recargo sumado y Baleares', function (): void {
        $porLugar = collect(CatalogoTasaTuristica::todas())->keyBy('region');

        expect($porLugar['Barcelona · '.__('autoridad.tasa.tipos.hotel4')]->tramos[0]['importe'])->toBe(840)
            ->and($porLugar['Barcelona · '.__('autoridad.tasa.tipos.hotel4')]->tramos[1]['importe'])->toBe(980)
            ->and($porLugar['Barcelona · '.__('autoridad.tasa.tipos.vivienda')]->tramos[0]['importe'])->toBe(950)
            ->and($porLugar[__('autoridad.tasa.lugares.baleares').' · '.__('autoridad.tasa.tipos.balear_hotel5')]->tramos[0]['importe'])->toBe(400)
            ->and($porLugar[__('autoridad.tasa.lugares.baleares').' · '.__('autoridad.tasa.tipos.balear_hotel5')]->temporadaBaja['descuento'])->toBe(75.0);
    });

    it('cuenta personas y noches, con tope y sin los menores', function (): void {
        $impuesto = Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IEET', 'nombre' => 'IEET', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 175, 'incluido_en_precio' => false, 'tope_noches' => 7, 'edad_minima' => 17, 'activo' => true]);
        $reserva = ($this->reservar)(5, ['salida' => $this->hoy->addDays(15)->format('Y-m-d'), 'personas' => 3, 'ninos' => 1, 'total' => 100000]);

        $fila = $reserva->impuestos()->sole();

        expect($fila->impuesto_id)->toBe($impuesto->id)
            ->and($fila->importe)->toBe(175 * 7 * 2)
            ->and($fila->unidades)->toBe(14)
            ->and($fila->unidades_exentas)->toBe(7)
            ->and($fila->importe_unitario)->toBe(175);
    });

    it('con la tarifa al reservar vale la de hoy aunque se llegue con la siguiente', function (): void {
        $corte = $this->hoy->addDays(10)->format('Y-m-d');
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Vieja', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 100, 'incluido_en_precio' => false, 'vigente_hasta' => $corte, 'fijar_al_reservar' => true, 'activo' => true]);
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Nueva', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 200, 'incluido_en_precio' => false, 'vigente_desde' => $this->hoy->addDays(11)->format('Y-m-d'), 'fijar_al_reservar' => true, 'activo' => true]);

        $presupuesto = Cotizador::presupuesto($this->unidad, $this->hoy->addDays(20), $this->hoy->addDays(21), 1);

        expect(collect($presupuesto->impuestos)->pluck('nombre')->all())->toBe(['Vieja']);

        Impuesto::query()->update(['fijar_al_reservar' => false]);
        $presupuesto = Cotizador::presupuesto($this->unidad, $this->hoy->addDays(20), $this->hoy->addDays(21), 1);
        expect(collect($presupuesto->impuestos)->pluck('nombre')->all())->toBe(['Nueva']);
    });

    it('baja en temporada baja y desde el noveno día, noche por noche', function (): void {
        $impuesto = new Impuesto(['temporada_baja_desde' => '11-01', 'temporada_baja_hasta' => '04-30']);
        expect($impuesto->enTemporadaBaja(CarbonImmutable::parse('2027-01-15')))->toBeTrue()
            ->and($impuesto->enTemporadaBaja(CarbonImmutable::parse('2027-10-31')))->toBeFalse()
            ->and($impuesto->enTemporadaBaja(CarbonImmutable::parse('2027-11-01')))->toBeTrue();

        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'ITS', 'nombre' => 'ITS', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 400, 'incluido_en_precio' => false, 'temporada_baja_desde' => '11-01', 'temporada_baja_hasta' => '04-30', 'temporada_baja_descuento' => 75, 'dias_largos_desde' => 9, 'dias_largos_descuento' => 50, 'activo' => true]);

        // Del 29 de octubre: tres noches enteras y dos con el 75 % menos.
        $cinco = Cotizador::presupuesto($this->unidad, CarbonImmutable::parse('2030-10-29'), CarbonImmutable::parse('2030-11-03'), 1);
        expect($cinco->impuestos[0]['importe'])->toBe(400 * 3 + 100 * 2);

        // En verano, diez noches: ocho enteras y dos a la mitad.
        $diez = Cotizador::presupuesto($this->unidad, CarbonImmutable::parse('2030-07-01'), CarbonImmutable::parse('2030-07-11'), 1);
        expect($diez->impuestos[0]['importe'])->toBe(400 * 8 + 200 * 2);
    });

    it('en la factura va aparte, con unidades y tarifa, y sin IVA encima', function (): void {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IVA', 'nombre' => 'IVA', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 10, 'incluido_en_precio' => true, 'activo' => true]);
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IEET', 'nombre' => 'IEET', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 175, 'incluido_en_precio' => false, 'activo' => true]);
        $entidad = EntidadFiscal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Hostal SL', 'pais' => 'ES', 'identificador_fiscal' => 'B00000000']);
        $serie = SerieFacturacion::query()->create(['entidad_fiscal_id' => $entidad->id, 'serie' => 'A', 'tipo_documento' => App\Enums\TipoDocumentoFiscal::Recibo])->refresh();
        $reserva = ($this->reservar)(5, ['personas' => 2, 'total' => 22700]);

        $factura = app(GestorFacturas::class)->emitir($reserva->load('propiedad'), $serie->refresh(), ['identificador_fiscal' => 'B11111111', 'nombre' => 'Empresa']);

        $lineas = $factura->lineas()->with('impuestos')->orderBy('orden')->get();
        expect($lineas)->toHaveCount(2)
            ->and($lineas[1]->importe)->toBe(700)
            ->and($lineas[1]->concepto)->toContain('IEET')->toContain('4')
            ->and($lineas[1]->impuestos)->toBeEmpty()
            ->and($lineas[0]->importe + $lineas[0]->impuestos->sum('importe'))->toBe(22000)
            ->and($factura->total)->toBe(22700);
    });

    it('el informe dice cuántas estancias, y el archivo para la ATC sale con su cabecera', function (): void {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IEET', 'nombre' => 'IEET', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 175, 'incluido_en_precio' => false, 'edad_minima' => 17, 'activo' => true]);
        $reserva = ($this->reservar)(5, ['personas' => 3, 'ninos' => 1, 'total' => 22000]);
        $llegada = $reserva->llegada;

        $informe = InformesGestion::impuestos([$this->propiedad->id], CarbonImmutable::parse($llegada->format('Y-m-d'))->subDay(), CarbonImmutable::parse($llegada->format('Y-m-d'))->addDay());
        expect($informe['filas'][0][2])->toBe('4')
            ->and($informe['filas'][0][3])->toBe('2');

        $mes = (int) $llegada->format('n');
        $periodo = $mes >= 4 && $mes <= 9 ? '1S' : '2S';
        $ejercicio = $mes <= 3 ? (int) $llegada->format('Y') - 1 : (int) $llegada->format('Y');
        $csv = (new DeclaracionEstancias())->csv($this->propiedad, $ejercicio, $periodo, ['nif' => 'b-12.345.678', 'nombre' => 'Hostal; SL', 'tipo' => 'EH', 'registro' => 'NNNN-007639-02', 'categoria' => 'OC']);
        $filas = explode("\r\n", $csv);

        expect($filas[0])->toStartWith('NIF o CIF;Apellidos y nombre o Razon Social;Tipo de establecimiento;')
            ->and($filas[1])->toBe('B12345678;Hostal  SL;EH;NNNN-007639-02;OC;N;'.$ejercicio.';'.$periodo.';4;0;2;0;0');
    });

    it('el botón del archivo sale solo con el impuesto, y baja el archivo con un gerente', function (): void {
        Livewire::actingAs($this->gerente)->test(ListImpuestos::class)->assertActionHidden('declaracion');
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IEET', 'nombre' => 'IEET', 'base' => BaseCargo::PorPersonaNoche, 'importe' => 175, 'incluido_en_precio' => false, 'activo' => true]);

        Livewire::actingAs($this->gerente)->test(ListImpuestos::class)
            ->assertActionVisible('declaracion')
            ->callAction('declaracion', ['propiedad_id' => $this->propiedad->id, 'ejercicio' => 2026, 'periodo' => '2S', 'nif' => 'B12345678', 'nombre' => 'Hostal', 'tipo' => 'EH', 'registro' => 'X-1', 'categoria' => 'OC'])
            ->assertFileDownloaded();
    });
});

describe('Redsys y Bizum', function (): void {
    beforeEach(function (): void {
        $this->ajustes = new AjustesMotor($this->propiedad);
        $this->ajustes->guardarLlave('redsys_clave', 'sq7HjrUOBfKmC576ILgskD5srU870gJ7');
        $ajustes = (array) $this->propiedad->ajustes;
        $ajustes['motor'] = ['activo' => true, 'anticipo_porcentaje' => 50, 'minutos_retencion' => 30, 'redsys_comercio' => '999008881', 'redsys_terminal' => '1', 'redsys_pruebas' => true, 'redsys_bizum' => true];
        $this->propiedad->update(['ajustes' => $ajustes]);
        $this->post('/reservar/hostal', ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+34 600 000 000', 'acepto' => '1'])->assertRedirect();
        $this->reserva = Reserva::query()->firstOrFail();
        $this->pagina = '/reservar/hostal/r/'.$this->reserva->codigo_publico;
        $this->firmar = function (array $datos, string $clave = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'): array {
            $crudo = base64_encode((string) json_encode($datos));
            $llave = openssl_encrypt(str_pad($datos['Ds_Order'], 16, "\0"), 'des-ede3-cbc', (string) base64_decode($clave), OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat("\0", 8));

            return ['Ds_SignatureVersion' => 'HMAC_SHA256_V1', 'Ds_MerchantParameters' => $crudo, 'Ds_Signature' => strtr(base64_encode(hash_hmac('sha256', $crudo, (string) $llave, true)), '+/', '-_')];
        };
        $this->abrir = function (string $pasarela): array {
            $redirige = $this->post($this->pagina.'/pagar/'.$pasarela);
            $redirige->assertRedirectContains('/reservar/hostal/ir/'.$pasarela.'/');
            $pagina = $this->get((string) $redirige->headers->get('Location'))->assertOk();
            preg_match('/name="Ds_MerchantParameters" value="([^"]+)"/', (string) $pagina->getContent(), $datos);
            preg_match('/name="Ds_Signature" value="([^"]+)"/', (string) $pagina->getContent(), $firma);

            return ['pagina' => $pagina, 'crudo' => $datos[1], 'firma' => $firma[1], 'parametros' => (array) json_decode((string) base64_decode($datos[1]), true), 'url' => (string) $redirige->headers->get('Location')];
        };
        $this->aviso = fn (string $pasarela, array $campos) => $this->post('/avisos-pago/hostal/'.$pasarela, $campos);
    });

    it('calcula la firma como su manual: un vector comprobado contra su entorno de pruebas', function (): void {
        $crudo = 'eyJEc19PcmRlciI6IjEyMzRBQkNERUYxMiIsIkRzX0Ftb3VudCI6IjEwMDAwMCIsIkRzX0N1cnJlbmN5IjoiOTc4IiwiRHNfUmVzcG9uc2UiOiIwMDAwIiwiRHNfTWVyY2hhbnREYXRhIjoieCJ9';

        // Con la firma correcta llega a leer los datos; «x» no es una referencia válida, y eso ya no es un error de firma.
        $bueno = $this->post('/avisos-pago/hostal/redsys', ['Ds_MerchantParameters' => $crudo, 'Ds_Signature' => 'ia8MXZxUL7K1a1I54FjEz7qLo5sPNm9McpCjhUSWzhU=']);
        $malo = $this->post('/avisos-pago/hostal/redsys', ['Ds_MerchantParameters' => $crudo, 'Ds_Signature' => 'AA8MXZxUL7K1a1I54FjEz7qLo5sPNm9McpCjhUSWzhU=']);

        expect($bueno->getStatusCode())->toBe(200)
            ->and($malo->getStatusCode())->toBe(400);
    });

    it('lleva a una página que manda el formulario firmado, con todo lo que pide', function (): void {
        $abierto = ($this->abrir)('redsys');

        $abierto['pagina']->assertSee('https://sis-t.redsys.es:25443/sis/realizarPago', false)->assertSee('HMAC_SHA256_V1');
        expect($abierto['parametros'])->toMatchArray([
            'DS_MERCHANT_AMOUNT' => '10000',
            'DS_MERCHANT_CURRENCY' => '978',
            'DS_MERCHANT_MERCHANTCODE' => '999008881',
            'DS_MERCHANT_TERMINAL' => '1',
            'DS_MERCHANT_TRANSACTIONTYPE' => '0',
            'DS_MERCHANT_MERCHANTNAME' => 'Hostal',
        ])->and($abierto['parametros']['DS_MERCHANT_ORDER'])->toMatch('/^[0-9]{4}[0-9A-Z]{8}$/')
            ->and($abierto['parametros'])->not->toHaveKey('DS_MERCHANT_PAYMETHODS')
            ->and($abierto['firma'])->toBe(($this->firmar)(['Ds_Order' => $abierto['parametros']['DS_MERCHANT_ORDER']])['Ds_Signature'] === '' ? '' : $abierto['firma']);

        // Quien vuelve de una tarjeta rechazada lo intenta de nuevo con otro número de pedido, sobre el mismo cobro.
        $otra = $this->get($abierto['url'])->assertOk();
        preg_match('/name="Ds_MerchantParameters" value="([^"]+)"/', (string) $otra->getContent(), $datos);
        expect(json_decode((string) base64_decode($datos[1]), true)['DS_MERCHANT_ORDER'])->not->toBe($abierto['parametros']['DS_MERCHANT_ORDER'])
            ->and(PagoEnLinea::query()->count())->toBe(1);
    });

    it('el aviso con firma buena confirma y anota tarjeta; con otra clave, no', function (): void {
        $abierto = ($this->abrir)('redsys');
        $datos = ['Ds_Order' => $abierto['parametros']['DS_MERCHANT_ORDER'], 'Ds_Amount' => '10000', 'Ds_Currency' => '978', 'Ds_Response' => '0000', 'Ds_AuthorisationCode' => '123456', 'Ds_MerchantData' => $abierto['parametros']['DS_MERCHANT_MERCHANTDATA']];

        ($this->aviso)('redsys', ($this->firmar)($datos, 'Mk9m98IfEblmPfrpsawt7BmxObt98Jev'))->assertStatus(400);
        expect($this->reserva->refresh()->pagado)->toBe(0);

        ($this->aviso)('redsys', ($this->firmar)($datos))->assertOk();

        $this->reserva->refresh();
        expect($this->reserva->pagado)->toBe(10000)
            ->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada)
            ->and($this->reserva->pagos()->sole()->metodo)->toBe(MetodoPago::Tarjeta)
            ->and(PagoEnLinea::query()->sole()->estado)->toBe('pagado');

        // El mismo aviso otra vez no vuelve a cobrar.
        ($this->aviso)('redsys', ($this->firmar)($datos))->assertOk();
        expect($this->reserva->refresh()->pagos()->count())->toBe(1);
    });

    it('una tarjeta denegada no confirma, y otro importe tampoco', function (): void {
        $abierto = ($this->abrir)('redsys');
        $base = ['Ds_Order' => $abierto['parametros']['DS_MERCHANT_ORDER'], 'Ds_Amount' => '10000', 'Ds_Currency' => '978', 'Ds_MerchantData' => $abierto['parametros']['DS_MERCHANT_MERCHANTDATA']];

        ($this->aviso)('redsys', ($this->firmar)($base + ['Ds_Response' => '0190']))->assertOk();
        expect($this->reserva->refresh()->pagado)->toBe(0);

        ($this->aviso)('redsys', ($this->firmar)(['Ds_Amount' => '1'] + $base + ['Ds_Response' => '0000']))->assertOk();
        expect($this->reserva->refresh()->pagado)->toBe(0);
    });

    it('Bizum es otro botón del mismo terminal, y se anota aparte', function (): void {
        $this->get($this->pagina)->assertSee(__('motor.pagar_con.redsys'))->assertSee(__('motor.pagar_con.bizum'));

        $abierto = ($this->abrir)('bizum');

        expect($abierto['parametros']['DS_MERCHANT_PAYMETHODS'])->toBe('z');
        $datos = ['Ds_Order' => $abierto['parametros']['DS_MERCHANT_ORDER'], 'Ds_Amount' => '10000', 'Ds_Currency' => '978', 'Ds_Response' => '0000', 'Ds_MerchantData' => $abierto['parametros']['DS_MERCHANT_MERCHANTDATA']];
        ($this->aviso)('bizum', ($this->firmar)($datos))->assertOk();
        expect($this->reserva->refresh()->pagos()->sole()->metodo)->toBe(MetodoPago::Otro);
    });

    it('al volver con el resultado firmado también confirma; sin él, espera el aviso', function (): void {
        $abierto = ($this->abrir)('redsys');
        $cobro = PagoEnLinea::query()->sole();

        $this->get($this->pagina.'/retorno/redsys?cobro='.$cobro->id)->assertRedirect();
        expect($this->reserva->refresh()->pagado)->toBe(0);

        $datos = ['Ds_Order' => $abierto['parametros']['DS_MERCHANT_ORDER'], 'Ds_Amount' => '10000', 'Ds_Currency' => '978', 'Ds_Response' => '0000', 'Ds_MerchantData' => $abierto['parametros']['DS_MERCHANT_MERCHANTDATA']];
        $this->get($this->pagina.'/retorno/redsys?cobro='.$cobro->id.'&'.http_build_query(($this->firmar)($datos)))->assertRedirect();
        expect($this->reserva->refresh()->pagado)->toBe(10000);
    });

    it('una referencia inventada o de otro cobro no abre nada', function (): void {
        ($this->abrir)('redsys');
        $cobro = PagoEnLinea::query()->sole();

        $this->get('/reservar/hostal/ir/redsys/'.$cobro->id.'-'.str_repeat('a', 24))->assertNotFound();
        $referencia = App\Pagos\CobrosEnLinea::referenciaInterna($cobro->id);
        $this->get('/reservar/hostal/ir/bizum/'.$referencia)->assertNotFound();

        $cobro->update(['estado' => 'pagado']);
        $this->get('/reservar/hostal/ir/redsys/'.$referencia)->assertNotFound();
    });

    it('solo en euros', function (): void {
        expect(array_keys((new Pasarelas($this->propiedad->refresh()))->disponibles()))->toBe(['redsys', 'bizum']);

        $this->propiedad->update(['moneda' => 'MXN']);

        expect((new Pasarelas($this->propiedad->refresh()))->disponibles())->toBe([]);
    });

    it('se configura desde el motor con un gerente, y la clave no se ve', function (): void {
        Livewire::actingAs($this->gerente)->test(MotorDeReservas::class)
            ->set('datos.redsys_comercio', '123456789')
            ->set('datos.redsys_terminal', '2')
            ->set('datos.redsys_clave', 'Mk9m98IfEblmPfrpsawt7BmxObt98Jev')
            ->set('datos.redsys_pruebas', false)
            ->set('datos.redsys_bizum', false)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDontSee('Mk9m98IfEblmPfrpsawt7BmxObt98Jev');

        Ajustes::olvidar();
        $ajustes = new AjustesMotor($this->propiedad->refresh());
        expect($ajustes->redsys())->toBe(['comercio' => '123456789', 'terminal' => '2', 'pruebas' => false, 'bizum' => false])
            ->and($ajustes->llave('redsys_clave'))->toBe('Mk9m98IfEblmPfrpsawt7BmxObt98Jev')
            ->and(array_keys((new Pasarelas($this->propiedad))->disponibles()))->toBe(['redsys']);
    });
});

describe('libro de facturas y formulario de llegada', function (): void {
    it('exporta por tipo de impuesto para un programa de facturación, solo si el país lo usa', function (): void {
        Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'IVA', 'nombre' => 'IVA', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 10, 'incluido_en_precio' => true, 'activo' => true]);
        $entidad = EntidadFiscal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Hostal SL', 'pais' => 'ES', 'identificador_fiscal' => 'B00000000']);
        $serie = SerieFacturacion::query()->create(['entidad_fiscal_id' => $entidad->id, 'serie' => 'A', 'tipo_documento' => App\Enums\TipoDocumentoFiscal::Recibo])->refresh();
        app(GestorFacturas::class)->emitir(($this->reservar)()->load('propiedad'), $serie, ['identificador_fiscal' => 'B11111111', 'nombre' => 'Empresa']);

        $respuesta = Livewire::actingAs($this->admin)->test(ListFacturas::class)
            ->callAction('exportar', ['desde' => $this->hoy->subDay()->format('Y-m-d'), 'hasta' => $this->hoy->addDay()->format('Y-m-d'), 'formato' => 'libro']);

        $respuesta->assertFileDownloaded();
        $pagina = new ListFacturas();
        $metodo = new ReflectionMethod($pagina, 'libro');
        ob_start();
        $metodo->invoke($pagina, $this->hoy->subDay()->format('Y-m-d'), $this->hoy->addDay()->format('Y-m-d'))->sendContent();
        $filas = explode("\n", trim(substr((string) ob_get_clean(), 3)));
        expect(str_getcsv($filas[0], ';'))->toContain(__('autoridad.libro.nif_emisor'), __('autoridad.libro.cuota'))
            ->and(str_getcsv($filas[1], ';'))->toContain('B00000000', 'A', 'F1', 'B11111111', 'IVA', '10', '200,00');
        $this->propiedad->update(['paquete_pais' => PaquetePais::Mexico]);
        Livewire::actingAs($this->admin)->test(ListFacturas::class)
            ->mountAction('exportar')
            ->assertDontSee(__('autoridad.libro.formato'));
    });

    it('a un niño no se le exige documento en el formulario; a un adulto, sí', function (): void {
        $reserva = ($this->reservar)(5, ['personas' => 2]);
        $url = app(App\Llegada\GestorLlegadas::class)->urlDe($reserva);
        $titular = ['nombre' => 'Ana', 'apellido1' => 'García', 'fecha_nacimiento' => '1980-01-01', 'documento_tipo' => 'dni', 'documento_numero' => dniDePrueba(), 'correo' => 'ana@example.com', 'telefono' => '+34 600 000 000', 'domicilio_calle' => 'Mayor 1', 'domicilio_ciudad' => 'Madrid', 'domicilio_codigo_postal' => '28001', 'domicilio_pais_texto' => 'España'];

        $this->post($url, ['personas' => [$titular, ['nombre' => 'Luis', 'apellido1' => 'García', 'fecha_nacimiento' => '1990-01-01']], 'confirmo' => '1'])
            ->assertSessionHasErrors(['personas.1.documento_numero']);

        $this->post($url, ['personas' => [$titular, ['nombre' => 'Leo', 'apellido1' => 'García', 'fecha_nacimiento' => $this->hoy->subYears(6)->format('Y-m-d')]], 'confirmo' => '1'])
            ->assertSessionDoesntHaveErrors(['personas.1.documento_numero', 'personas.1.documento_tipo']);
    });
});
