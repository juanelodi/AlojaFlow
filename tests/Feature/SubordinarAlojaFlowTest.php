<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\OrigenEjecucion;
use App\Enums\OrigenFila;
use App\Enums\TipoMovimiento;
use App\Enums\TipoPrincipal;
use App\Enums\TipoReserva;
use App\Filament\Resources\Propiedades\Pages\CreatePropiedad;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\Propiedades\RelationManagers\ConexionesRelationManager;
use App\Models\BloqueoExterno;
use App\Models\Caja;
use App\Models\ConexionCompartida;
use App\Models\ContactoHuesped;
use App\Models\EventoReserva;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Reservas\GestorReservas;
use App\Sincronizacion\AlojaFlow\CodigoConexion;
use App\Sincronizacion\AlojaFlow\Exportador;
use App\Sincronizacion\AlojaFlow\GestorConexiones;
use App\Sincronizacion\AlojaFlow\Sincronizador;
use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\Subordinadores;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request as PeticionCliente;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

/**
 * La principal y la subordinada viven en la misma base de la prueba, como dos propiedades. La «red» se simula pasando cada
 * petición por las rutas reales de esta misma aplicación.
 */
beforeEach(function () {
    config(['alojaflow.subordinacion.red_local' => true, 'app.url' => 'http://127.0.0.1:9/']);
    $this->origen = Propiedad::query()->create(['nombre' => 'Origen', 'codigo' => 'origen', 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'moneda' => 'USD', 'zona_horaria' => 'America/Cancun', 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->origen->id, 'codigo' => 'U1', 'nombre' => 'Cabaña 1', 'capacidad' => 4]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->origen->id, 'nombre' => 'Ana', 'apellido1' => 'Prueba', 'documento_tipo_texto' => 'Pasaporte', 'documento_numero' => 'ABC123']);
    ContactoHuesped::query()->create(['huesped_id' => $this->huesped->id, 'tipo' => 'correo', 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $hoy = CarbonImmutable::now('America/Cancun')->startOfDay();
    $this->reserva = app(GestorReservas::class)->crear($this->origen, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $hoy->addDays(3)->format('Y-m-d'), 'salida' => $hoy->addDays(5)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000,
    ]);

    $nueva = app(GestorConexiones::class)->crear($this->origen, 'Oficina', false);
    $this->conexion = $nueva['conexion'];
    $leido = CodigoConexion::leer($nueva['codigo']);
    $this->token = $leido['token'];

    $this->destino = Propiedad::query()->create(['nombre' => 'Copia', 'codigo' => 'copia', 'modo' => ModoPropiedad::Subordinada, 'pais' => 'MX', 'moneda' => 'MXN', 'zona_horaria' => 'UTC', 'idioma' => 'es']);
    $this->principal = new Principal(['tipo' => TipoPrincipal::AlojaFlow, 'url_base' => $leido['url'], 'contrasena' => $leido['token'], 'permitir_red_local' => true, 'frecuencia_minutos' => 30]);
    $this->principal->propiedad_id = $this->destino->id;
    $this->principal->save();

    // Cada petición del cliente entra por las rutas de verdad.
    $this->respuesta = null;
    Http::fake(function (PeticionCliente $peticion) {
        if ($this->respuesta !== null) {
            return ($this->respuesta)($peticion);
        }
        $ruta = (string) parse_url($peticion->url(), PHP_URL_PATH);
        $consulta = (string) parse_url($peticion->url(), PHP_URL_QUERY);
        $respuesta = $this->withHeaders(['Authorization' => $peticion->header('Authorization')[0] ?? '', 'Accept' => 'application/json'])->getJson($ruta.($consulta !== '' ? '?'.$consulta : ''));

        return Http::response($respuesta->getContent(), $respuesta->getStatusCode(), ['Content-Type' => 'application/json']);
    });
    $this->copiar = fn (OrigenEjecucion $origen = OrigenEjecucion::Manual) => app(Sincronizador::class)->sincronizar($this->principal->refresh(), $origen);
});

function copiaDe(Reserva $original): ?Reserva
{
    return Reserva::query()->where('clave_origen', (string) $original->id)->where('sincronizada', true)->first();
}

describe('código de conexión', function () {
    it('se lee lo que se armó, y otra cosa no', function () {
        $codigo = CodigoConexion::armar('https://hotel.example.com/', str_repeat('a', 64));

        expect(CodigoConexion::leer("  $codigo\n"))->toBe(['url' => 'https://hotel.example.com/', 'token' => str_repeat('a', 64)])
            ->and(CodigoConexion::leer('hola'))->toBeNull()
            ->and(CodigoConexion::leer('AF1-no-es-json'))->toBeNull()
            ->and(CodigoConexion::leer(CodigoConexion::armar('https://x/', 'corto')))->toBeNull();
    });

    it('de la conexión solo se guarda la huella, y una subordinada no se puede compartir', function () {
        expect($this->conexion->token_hash)->toBe(hash('sha256', $this->token))
            ->and(ConexionCompartida::query()->where('token_hash', $this->token)->exists())->toBeFalse();

        expect(fn () => app(GestorConexiones::class)->crear($this->destino, 'Cadena', false))->toThrow(InvalidArgumentException::class);
    });
});

describe('lo que expone la principal', function () {
    it('sin token, con uno inventado o revocado no entrega nada', function () {
        $this->getJson('/api/subordinacion/v1/estado')->assertUnauthorized();
        $this->withToken(str_repeat('a', 64))->getJson('/api/subordinacion/v1/estado')->assertUnauthorized();

        $this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->assertOk()->assertJsonPath('api', 1);

        app(GestorConexiones::class)->revocar($this->conexion);
        $this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->assertUnauthorized();
    });

    it('con el módulo apagado no existe, y no deja escribir', function () {
        $this->withToken($this->token)->postJson('/api/subordinacion/v1/estado')->assertStatus(405);

        Modulos::guardar([]);
        $this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->assertNotFound();
    });

    it('el código público de la reserva no viaja', function () {
        $fila = $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/reservas')->assertOk()->json('filas.0');

        expect($fila)->not->toHaveKey('codigo_publico')
            ->and(json_encode($fila))->not->toContain((string) $this->reserva->codigo_publico);
    });

    it('solo entrega la propiedad de esa conexión', function () {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'moneda' => 'MXN', 'zona_horaria' => 'UTC', 'idioma' => 'es']);
        Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'De otra', 'capacidad' => 1]);

        $filas = $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/unidades')->assertOk()->json('filas');

        expect(array_column($filas, 'nombre'))->toBe(['Cabaña 1']);
        $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/usuarios')->assertNotFound();
    });

    it('el documento de identidad viaja solo si la conexión lo permite', function () {
        $sin = $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/huespedes')->assertOk()->json('filas.0');
        expect($sin)->not->toHaveKey('documento_numero')->not->toHaveKey('documento_tipo_texto')
            ->and($sin['contactos'][0]['valor'])->toBe('ana@example.com')
            ->and($sin['nombre'])->toBe('Ana');

        $this->conexion->update(['incluir_documentos' => true]);
        $con = $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/huespedes')->json('filas.0');
        expect($con['documento_numero'])->toBe('ABC123')
            ->and($con['documento_tipo_texto'])->toBe('Pasaporte');
    });

    it('una propiedad que dejó de ser independiente deja de compartirse', function () {
        $this->origen->update(['modo' => ModoPropiedad::Subordinada]);

        $this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->assertNotFound();
        $this->withToken($this->token)->getJson('/api/subordinacion/v1/datos/reservas')->assertNotFound();
    });

    it('la revisión cambia con lo que se comparte', function () {
        $primera = $this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->json('revision');
        expect($this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->json('revision'))->toBe($primera);

        ContactoHuesped::query()->create(['huesped_id' => $this->huesped->id, 'tipo' => 'telefono', 'valor' => '+525511112222', 'valor_normalizado' => '+525511112222', 'orden' => 1]);
        expect($this->withToken($this->token)->getJson('/api/subordinacion/v1/estado')->json('revision'))->not->toBe($primera);
    });
});

describe('copiar a la subordinada', function () {
    it('copia también la caja, uno por uno, y lo que quita la principal se va', function () {
        $caja = Caja::query()->create(['propiedad_id' => $this->origen->id, 'nombre' => 'Recepción', 'activa' => true]);
        $pago = app(GestorReservas::class)->registrarPago($this->reserva, 50000, MetodoPago::Transferencia, $this->reserva->llegada, 'REF-1', $caja->id);
        $movimiento = Movimiento::query()->create(['propiedad_id' => $this->origen->id, 'caja_id' => $caja->id, 'fecha' => '2026-11-01', 'tipo' => TipoMovimiento::Gasto, 'concepto' => 'Luz', 'metodo' => MetodoPago::Efectivo, 'importe' => 1500, 'moneda' => 'USD']);

        expect(($this->copiar)()->estado)->toBe(EstadoEjecucion::Correcta);
        $cajaCopia = Caja::query()->where('propiedad_id', $this->destino->id)->sole();
        $pagoCopia = Pago::query()->where('propiedad_id', $this->destino->id)->sole();
        expect($cajaCopia->nombre)->toBe('Recepción')->and($cajaCopia->sincronizada)->toBeTrue()
            ->and($pagoCopia->importe)->toBe(50000)->and($pagoCopia->metodo)->toBe(MetodoPago::Transferencia)->and($pagoCopia->referencia)->toBe('REF-1')
            ->and($pagoCopia->caja_id)->toBe($cajaCopia->id)->and($pagoCopia->reserva_id)->toBe(copiaDe($this->reserva)?->id);
        $movimientoCopia = Movimiento::query()->where('propiedad_id', $this->destino->id)->sole();
        expect($movimientoCopia->concepto)->toBe('Luz')->and($movimientoCopia->importe)->toBe(1500);
        // Nunca escribe en la principal.
        expect($pago->refresh()->sincronizada)->toBeFalse();

        $revision = app(Exportador::class)->estado($this->conexion->refresh())['revision'];
        $movimiento->delete();
        expect(app(Exportador::class)->estado($this->conexion)['revision'])->not->toBe($revision);
        expect(($this->copiar)()->estado)->toBe(EstadoEjecucion::Correcta);
        expect(Movimiento::query()->where('propiedad_id', $this->destino->id)->count())->toBe(0);
    });

    it('una principal anterior, que no ofrece la caja, se sigue copiando', function () {
        Caja::query()->create(['propiedad_id' => $this->origen->id, 'nombre' => 'No se pide', 'activa' => true]);
        $real = fn (PeticionCliente $peticion) => $this->withHeaders(['Authorization' => $peticion->header('Authorization')[0] ?? '', 'Accept' => 'application/json'])
            ->get((string) parse_url($peticion->url(), PHP_URL_PATH).'?'.(string) parse_url($peticion->url(), PHP_URL_QUERY));
        $this->respuesta = function (PeticionCliente $peticion) use ($real) {
            $respuesta = $real($peticion);
            $datos = json_decode((string) $respuesta->getContent(), true);
            if (is_array($datos) && isset($datos['colecciones'])) {
                $datos['colecciones'] = ['cajas_no_se_ofrecen'];
            }
            expect((string) $peticion->url())->not->toContain('/cajas');

            return Http::response(json_encode($datos), $respuesta->getStatusCode(), ['Content-Type' => 'application/json']);
        };

        expect(($this->copiar)()->estado)->toBe(EstadoEjecucion::Correcta);
        expect(copiaDe($this->reserva))->not->toBeNull()
            ->and(Caja::query()->where('propiedad_id', $this->destino->id)->count())->toBe(0);
    });

    it('copia unidades, huéspedes, reservas y noches, y toma la moneda de la principal', function () {
        $ejecucion = ($this->copiar)();

        expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta);
        $copia = copiaDe($this->reserva);
        expect($copia)->not->toBeNull()
            ->and($copia->propiedad_id)->toBe($this->destino->id)
            ->and($copia->origen)->toBe(OrigenFila::OtraInstalacion)
            ->and($copia->total)->toBe(200000)
            ->and($copia->titular->nombre)->toBe('Ana')
            ->and($copia->titular->contactos()->first()->valor)->toBe('ana@example.com')
            // Sin el documento: la conexión no lo permite.
            ->and($copia->titular->getAttributes()['documento_numero'])->toBeNull()
            ->and($copia->unidad->nombre)->toBe('Cabaña 1')
            ->and(DB::table('reservas_noches')->where('reserva_id', $copia->id)->count())->toBe(2)
            ->and($this->destino->refresh()->moneda)->toBe('USD')
            ->and($this->destino->zona_horaria)->toBe('America/Cancun')
            // La primera carga no son cambios.
            ->and(EventoReserva::query()->where('propiedad_id', $this->destino->id)->count())->toBe(0);
        // Nunca escribe en la principal.
        expect($this->reserva->refresh()->sincronizada)->toBeFalse()
            ->and(Reserva::query()->where('propiedad_id', $this->origen->id)->count())->toBe(1);
    });

    it('con el documento permitido, lo guarda cifrado', function () {
        $this->conexion->update(['incluir_documentos' => true]);

        ($this->copiar)();

        $crudo = copiaDe($this->reserva)->titular->getAttributes()['documento_numero'];
        expect($crudo)->not->toBe('ABC123')
            ->and(Crypt::decryptString($crudo))->toBe('ABC123');
    });

    it('los cambios en la principal llegan como eventos, y lo cancelado deja libres las noches', function () {
        ($this->copiar)();

        app(GestorReservas::class)->cancelar($this->reserva);
        ($this->copiar)();

        $copia = copiaDe($this->reserva);
        expect($copia->estado)->toBe(EstadoReserva::Cancelada)
            ->and(DB::table('reservas_noches')->where('reserva_id', $copia->id)->count())->toBe(0)
            ->and(EventoReserva::query()->where('reserva_id', $copia->id)->pluck('tipo')->map->value->all())->toContain('cancelada');
    });

    it('si nada cambió, una sincronización programada no baja nada, pero cada tanto sí', function () {
        ($this->copiar)();
        Http::assertSentCount(8);

        $ejecucion = ($this->copiar)(OrigenEjecucion::Programada);
        expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta)
            ->and($ejecucion->bytes_descargados)->toBe(0);
        Http::assertSentCount(9);

        $this->travel(7)->hours();
        ($this->copiar)(OrigenEjecucion::Programada);
        Http::assertSentCount(17);
    });

    it('los bloqueos de los portales se ven como bloqueos, y se van cuando se quitan', function () {
        $fuente = FuenteIcal::query()->create(['propiedad_id' => $this->origen->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Airbnb', 'url' => 'https://example.com/a.ics']);
        $bloqueo = BloqueoExterno::query()->create(['fuente_id' => $fuente->id, 'propiedad_id' => $this->origen->id, 'unidad_id' => $this->unidad->id, 'uid' => 'x1', 'llegada' => now()->addDays(20)->format('Y-m-d'), 'salida' => now()->addDays(22)->format('Y-m-d'), 'primera_vez_en' => now(), 'visto_en' => now()]);

        ($this->copiar)();

        $copia = Reserva::query()->where('clave_origen', 'b:'.$bloqueo->id)->first();
        expect($copia->tipo)->toBe(TipoReserva::Bloqueo)
            ->and($copia->origen_texto)->toBe('Airbnb')
            ->and($copia->urlEnPrincipal())->toBeNull();

        $bloqueo->update(['eliminado_en' => now()]);
        ($this->copiar)();
        expect($copia->refresh()->eliminada_en_principal_en)->not->toBeNull();
    });

    it('si se corta a la mitad, lo anterior sigue intacto y no quedan archivos', function () {
        ($this->copiar)();
        $total = copiaDe($this->reserva)->total;
        $this->reserva->forceFill(['total' => 999])->saveQuietly();

        $this->respuesta = (fn (PeticionCliente $p) => str_contains($p->url(), 'datos/reservas') ? Http::response('roto', 500) : Http::response(['api' => 1, 'version' => '1.0.12', 'revision' => 'otra', 'propiedad' => [], 'filas' => [], 'siguiente' => null]));
        $ejecucion = ($this->copiar)();

        expect($ejecucion->estado)->toBe(EstadoEjecucion::Fallida)
            ->and($ejecucion->codigo_error)->toBe(CodigoError::Conexion->value)
            ->and(copiaDe($this->reserva)->total)->toBe($total)
            ->and(glob(storage_path('app/private/subordinacion/'.$this->principal->id.'/*')))->toBe([]);
    });

    it('un código revocado pausa la principal y no reintenta; uno nuevo la reanuda', function () {
        app(GestorConexiones::class)->revocar($this->conexion);

        $ejecucion = ($this->copiar)();

        expect($ejecucion->codigo_error)->toBe(CodigoError::ConexionRechazada->value)
            ->and($this->principal->refresh()->estado)->toBe(EstadoPrincipal::Pausada)
            ->and($this->principal->estaPausada())->toBeTrue();

        $nueva = app(GestorConexiones::class)->crear($this->origen, 'Otra', false);
        $this->principal->update(['contrasena' => CodigoConexion::leer($nueva['codigo'])['token']]);

        expect($this->principal->refresh()->estado)->toBe(EstadoPrincipal::Activa)
            ->and(($this->copiar)()->estado)->toBe(EstadoEjecucion::Correcta);
    });

    it('una respuesta que no es de AlojaFlow no se toma por buena, y el token no sale en el detalle', function () {
        $this->respuesta = fn () => Http::response('<html>hola</html>', 200);

        $ejecucion = ($this->copiar)();

        expect($ejecucion->estado)->toBe(EstadoEjecucion::Fallida)
            ->and($ejecucion->codigo_error)->toBe(CodigoError::PrincipalSinCompartir->value)
            ->and((string) $ejecucion->detalle)->not->toContain($this->token);
    });

    it('otra versión de la API pausa, porque reintentar no lo arregla', function () {
        $this->respuesta = fn () => Http::response(['api' => 99, 'version' => '9.9.9', 'revision' => 'x', 'propiedad' => []]);

        expect(($this->copiar)()->codigo_error)->toBe(CodigoError::VersionNoAdmitida->value)
            ->and($this->principal->refresh()->estado)->toBe(EstadoPrincipal::Pausada);
    });

    it('lo que llega se recorta y se comprueba: es dato ajeno', function () {
        $this->respuesta = function (PeticionCliente $p) {
            return match (true) {
                str_contains($p->url(), 'datos/unidades') => Http::response(['filas' => [['id' => 7, 'codigo' => str_repeat('x', 500), 'nombre' => ['no' => 'texto'], 'capacidad' => 'mucha']], 'siguiente' => null]),
                str_contains($p->url(), 'datos/huespedes') => Http::response(['filas' => [['id' => 8, 'nombre' => str_repeat('n', 500), 'sexo' => 'raro', 'idioma' => 'xx', 'fecha_nacimiento' => '2020-99-99', 'contactos' => [['tipo' => 'otro', 'valor' => 'x']]]], 'siguiente' => null]),
                str_contains($p->url(), 'datos/reservas') => Http::response(['filas' => [
                    ['id' => 9, 'unidad_id' => 7, 'titular_id' => 8, 'estado' => 'inventado', 'tipo' => 'raro', 'llegada' => '2030-01-01', 'salida' => '2030-01-03', 'total' => 'x', 'personas' => 900000, 'impuestos_porcentaje' => 123456],
                    ['id' => 10, 'unidad_id' => 7, 'llegada' => '2030-01-05', 'salida' => '2030-01-01'],
                    ['id' => 11, 'unidad_id' => 999, 'llegada' => '2030-01-05', 'salida' => '2030-01-07'],
                ], 'siguiente' => null]),
                str_contains($p->url(), 'datos/bloqueos') => Http::response(['filas' => [], 'siguiente' => null]),
                default => Http::response(['api' => 1, 'version' => '1.0.12', 'revision' => 'r', 'propiedad' => ['moneda' => 'no es moneda', 'zona_horaria' => 'Marte/Base']]),
            };
        };

        $ejecucion = ($this->copiar)();

        expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta)
            ->and($ejecucion->incidencias)->toMatchArray(['reservas_sin_fechas' => 1, 'reservas_sin_unidad' => 1]);
        $huesped = Huesped::query()->where('propiedad_id', $this->destino->id)->first();
        expect(mb_strlen($huesped->nombre))->toBe(100)
            ->and($huesped->getAttributes()['sexo'])->toBeNull()
            ->and($huesped->getAttributes()['idioma'])->toBeNull()
            ->and($huesped->getAttributes()['fecha_nacimiento'])->toBeNull()
            ->and($huesped->contactos()->count())->toBe(0);
        $reserva = Reserva::query()->where('propiedad_id', $this->destino->id)->first();
        expect($reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($reserva->tipo)->toBe(TipoReserva::Reserva)
            ->and($this->destino->refresh()->moneda)->toBe('MXN');
    });

    it('una unidad borrada en la principal y creada otra con el mismo código no atora la copia', function () {
        ($this->copiar)();
        $vieja = Unidad::query()->where('propiedad_id', $this->destino->id)->where('clave_origen', (string) $this->unidad->id)->first();

        // Sin reservas en medio: la nueva toma el código de la que ya no está.
        DB::table('reservas_noches')->where('unidad_id', $this->unidad->id)->delete();
        DB::table('reservas')->where('unidad_id', $this->unidad->id)->delete();
        DB::table('unidades')->where('id', $this->unidad->id)->delete();
        Unidad::query()->create(['propiedad_id' => $this->origen->id, 'codigo' => 'U1', 'nombre' => 'Cabaña nueva', 'capacidad' => 2]);

        expect(($this->copiar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(Unidad::query()->where('propiedad_id', $this->destino->id)->where('codigo', 'U1')->first()->nombre)->toBe('Cabaña nueva')
            ->and($vieja->refresh()->activa)->toBeFalse();
    });

    it('elige con qué copiar según el tipo de principal', function () {
        expect(Subordinadores::de($this->principal))->toBeInstanceOf(Sincronizador::class);

        $hotelDruid = new Principal(['tipo' => TipoPrincipal::HotelDruid]);
        expect(Subordinadores::de($hotelDruid))->toBeInstanceOf(App\Sincronizacion\HotelDruid\Sincronizador::class);
    });

    it('la copia lleva a la reserva en la principal', function () {
        ($this->copiar)();

        expect(copiaDe($this->reserva)->urlEnPrincipal())->toBe('http://127.0.0.1:9/reservas/'.$this->reserva->id);
    });
});

describe('pantallas', function () {
    beforeEach(function () {
        $this->super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
        Modulos::marcarPreguntas('preguntas');
    });

    it('la subordinada se crea pegando el código', function () {
        $codigo = app(GestorConexiones::class)->crear($this->origen, 'Otra', false)['codigo'];

        Livewire::actingAs($this->super)->test(CreatePropiedad::class)
            ->fillForm(['modo' => ModoPropiedad::Subordinada->value, 'nombre' => 'Nueva', 'codigo' => 'nueva', 'pais' => 'MX', 'paquete_pais' => 'mexico', 'idioma' => 'es', 'moneda' => 'MXN', 'zona_horaria' => 'UTC', 'principal.tipo' => 'alojaflow', 'principal.codigo_conexion' => $codigo, 'principal.frecuencia_minutos' => 30])
            ->call('create')
            ->assertHasNoFormErrors();

        $principal = Propiedad::query()->where('codigo', 'nueva')->first()->principal;
        expect($principal->tipo)->toBe(TipoPrincipal::AlojaFlow)
            ->and($principal->url_base)->toBe('http://127.0.0.1:9/')
            ->and($principal->contrasena)->toBe(CodigoConexion::leer($codigo)['token']);
    });

    it('al editar, sin código nuevo se conserva el actual; con uno nuevo se reemplaza', function () {
        $pantalla = fn () => Livewire::actingAs($this->super)->test(EditPropiedad::class, ['record' => $this->destino->getKey()]);
        $token = $this->principal->contrasena;

        $pantalla()->fillForm(['nombre' => 'Copia renombrada'])->call('save')->assertHasNoFormErrors();
        expect($this->principal->refresh()->contrasena)->toBe($token)
            ->and($this->principal->url_base)->toBe('http://127.0.0.1:9/')
            ->and($this->principal->tipo)->toBe(TipoPrincipal::AlojaFlow);

        $codigo = app(GestorConexiones::class)->crear($this->origen, 'Nueva', false)['codigo'];
        $pantalla()->fillForm(['principal.codigo_conexion' => $codigo])->call('save')->assertHasNoFormErrors();
        expect($this->principal->refresh()->contrasena)->toBe(CodigoConexion::leer($codigo)['token']);
    });

    it('un código que no lo es se rechaza en el formulario', function () {
        Livewire::actingAs($this->super)->test(CreatePropiedad::class)
            ->fillForm(['modo' => ModoPropiedad::Subordinada->value, 'nombre' => 'Mala', 'codigo' => 'mala', 'pais' => 'MX', 'paquete_pais' => 'mexico', 'idioma' => 'es', 'moneda' => 'MXN', 'zona_horaria' => 'UTC', 'principal.tipo' => 'alojaflow', 'principal.codigo_conexion' => 'cualquier cosa', 'principal.frecuencia_minutos' => 30])
            ->call('create')
            ->assertHasFormErrors(['principal.codigo_conexion']);
    });

    it('compartir se ve solo en las independientes y con el módulo prendido', function () {
        $this->actingAs($this->super);
        expect(ConexionesRelationManager::canViewForRecord($this->origen, EditPropiedad::class))->toBeTrue()
            ->and(ConexionesRelationManager::canViewForRecord($this->destino, EditPropiedad::class))->toBeFalse();

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Subordinacion)));
        expect(ConexionesRelationManager::canViewForRecord($this->origen, EditPropiedad::class))->toBeFalse();
    });

    it('la pantalla de edición abre con la lista de conexiones', function () {
        $this->actingAs($this->super)->get(App\Filament\Resources\Propiedades\PropiedadResource::getUrl('edit', ['record' => $this->origen]))
            ->assertOk()->assertSeeLivewire(ConexionesRelationManager::class);
    });

    it('al compartir sale el código una vez, y se puede revocar', function () {
        $pantalla = Livewire::actingAs($this->super)->test(ConexionesRelationManager::class, ['ownerRecord' => $this->origen, 'pageClass' => EditPropiedad::class])
            ->assertCanSeeTableRecords([$this->conexion]);

        $pantalla->callTableAction('revocar', $this->conexion);

        expect($this->conexion->refresh()->revocada_en)->not->toBeNull();
    });

    it('con un rol no se ve la pantalla de propiedades', function () {
        $empleado = User::factory()->create(['activo' => true, 'idioma' => 'es']);

        $this->actingAs($empleado)->get(App\Filament\Resources\Propiedades\PropiedadResource::getUrl('edit', ['record' => $this->origen]))->assertForbidden();
    });
});
