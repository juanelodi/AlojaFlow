<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoEjecucion;
use App\Enums\EstadoPrincipal;
use App\Enums\MetodoPeticion;
use App\Enums\OrigenEjecucion;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\EjecucionSincronizacion;
use App\Models\EventoReserva;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\Proyector;
use App\Sincronizacion\HotelDruid\Sincronizador;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConstructorCopia;

/*
 * Lo que pide la parte D de subordinacion.md y no cubre SincronizacionTest: los casos raros del protocolo, la
 * reserva que está en dos años y el paso de una generación del proyector a otra (D-173).
 */

beforeEach(function () {
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return ['93.184.215.14'];
        }
    });
    $propiedad = Propiedad::query()->create(['nombre' => 'Posada del Río', 'codigo' => 'rio', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
    $this->principal = $propiedad->principal()->create(['url_base' => 'https://principal.example/hd/', 'usuario' => 'copias', 'contrasena' => 'clave-secreta']);

    // Una principal fingida: sirve la copia de turno y, si se le dice, contesta otra cosa en algún paso.
    $fingida = new ArrayObject(['copia' => '', 'respuestas' => []]);
    $this->fingida = $fingida;
    Http::fake(function (Request $peticion) use ($fingida) {
        $datos = $peticion->data();
        $paso = match (true) {
            ($datos['crea_backup'] ?? null) === 'SI' => 'crear',
            ($datos['salva_backup'] ?? null) === 'SI' => 'descargar',
            ($datos['cancella_backup'] ?? null) === 'SI' => 'borrar',
            default => 'probar',
        };
        if (isset($fingida['respuestas'][$paso])) {
            return $fingida['respuestas'][$paso];
        }
        $copia = $fingida['copia'];

        return match ($paso) {
            'crear' => Http::response("<html>File created.<br>\n<!-- Backup_created --></html>"),
            'descargar' => Http::response($copia instanceof ConstructorCopia ? (isset($datos['compresso']) ? $copia->gzip() : $copia->texto()) : $copia),
            'borrar' => Http::response('<html>ok</html>'),
            default => Http::response('<html><form><button name="crea_backup" value="1"></button></form></html>'),
        };
    });
    $this->servir = function (ConstructorCopia|string $copia, array $respuestas = []) use ($fingida): void {
        $fingida['copia'] = $copia;
        $fingida['respuestas'] = $respuestas;
    };
    $this->sincronizar = fn (OrigenEjecucion $origen = OrigenEjecucion::Manual): EjecucionSincronizacion => app(Sincronizador::class)->sincronizar($this->principal->refresh(), $origen);
    $this->copia = fn (): ConstructorCopia => ConstructorCopia::nueva()->unidad('Cabaña 1', 4, 1)->unidad('Cabaña 2', 2, 2)
        ->cliente(1, 'Pérez', 'Ana')->cliente(2, 'Smith', 'John')
        ->reserva(2026, 1, 'Cabaña 1', 1, '2026-11-10', '2026-11-13', ['tariffa_tot' => '3000.00'])
        ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14');
});

describe('el protocolo', function () {
    it('baja la copia sin comprimir si así se configuró, y por GET en el modo compatible', function () {
        $this->principal->update(['compresion' => false, 'metodo' => MetodoPeticion::Get]);
        ($this->servir)(($this->copia)());

        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)->and(Reserva::query()->count())->toBe(2);
        Http::assertSent(fn (Request $peticion): bool => $peticion->method() === 'GET' && ($peticion->data()['salva_backup'] ?? null) === 'SI' && ! isset($peticion->data()['compresso']));
    });

    it('admite una principal con el esquema 3.08', function () {
        ($this->servir)(($this->copia)()->version('3.08'));

        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and($this->principal->refresh()->version_esquema)->toBe('3.08');
    });

    it('no sigue una redirección: prueba una vez con el año anterior y avisa adónde mandaba, sin su consulta', function () {
        ($this->servir)(($this->copia)(), ['crear' => Http::response('', 302, ['Location' => 'https://otro.example/entrada.php?clave=1'])]);

        $ejecucion = ($this->sincronizar)();

        expect($ejecucion->codigo_error)->toBe(CodigoError::RespuestaDesconocida->value)
            ->and($ejecucion->paso_fallido)->toBe('crear')
            ->and($ejecucion->detalle)->toContain('https://otro.example/entrada.php')->not->toContain('clave=1');
        $anios = collect(Http::recorded())->map(fn (array $par): string => (string) $par[0]->data()['anno'])->all();
        expect($anios)->toBe([now('America/Mexico_City')->format('Y'), (string) ((int) now('America/Mexico_City')->format('Y') - 1)]);
    });

    it('si en la descarga llega la página de acceso, pausa la principal y aun así pide borrar la copia', function () {
        ($this->servir)(($this->copia)(), ['descargar' => Http::response('<html><form><input type="password" name="password_phpr"></form></html>')]);

        $ejecucion = ($this->sincronizar)();

        expect($ejecucion->codigo_error)->toBe(CodigoError::Credenciales->value)
            ->and($ejecucion->paso_fallido)->toBe('descargar')
            ->and($this->principal->refresh()->estado)->toBe(EstadoPrincipal::Pausada);
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $peticion): bool => ($peticion->data()['cancella_backup'] ?? null) === 'SI');
    });

    it('rechaza una descarga que pasa del tamaño permitido', function () {
        config(['alojaflow.subordinacion.tamano_maximo_descarga' => 200]);
        ($this->servir)(($this->copia)());

        $ejecucion = ($this->sincronizar)();

        expect($ejecucion->codigo_error)->toBe(CodigoError::TamanoExcedido->value)->and($ejecucion->paso_fallido)->toBe('descargar');
    });

    it('reconoce un gzip cortado y conserva lo que había', function () {
        ($this->servir)(($this->copia)());
        ($this->sincronizar)();
        ($this->servir)(substr(($this->copia)()->gzip(), 0, 400));

        $ejecucion = ($this->sincronizar)();

        expect($ejecucion->codigo_error)->toBe(CodigoError::GzipDanado->value)
            ->and($ejecucion->paso_fallido)->toBe('descomprimir')
            ->and(Reserva::query()->whereNull('eliminada_en_principal_en')->count())->toBe(2)
            ->and(DB::table('hd_filas')->where('ejecucion_id', $ejecucion->id)->count())->toBe(0);
    });

    it('si la principal no borra su copia, la sincronización vale y queda un aviso', function () {
        ($this->servir)(($this->copia)(), ['borrar' => Http::response('<html>Error</html>', 500)]);

        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(Aviso::query()->where('tipo', Sincronizador::AVISO_BORRADO)->whereNull('resuelto_en')->exists())->toBeTrue();
    });

    it('la contraseña no llega al registro aunque la principal la repita, y la copia descargada no se queda en el disco', function () {
        ($this->servir)(($this->copia)(), ['crear' => Http::response('<html>No vale clave-secreta para copias</html>')]);
        $ejecucion = ($this->sincronizar)();
        expect($ejecucion->detalle)->toContain('No vale')->not->toContain('clave-secreta');

        ($this->servir)(($this->copia)());
        ($this->sincronizar)();
        expect(File::allFiles(storage_path('app/private/subordinacion')))->toBe([]);
    });

    it('la señal de que la copia se creó vale aunque la página traiga también el campo de la contraseña', function () {
        ($this->servir)(($this->copia)(), ['crear' => Http::response('<html><!-- Backup_created --><form><input type="hidden" name="password_phpr" value=""></form></html>')]);

        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta);
    });

    it('reconoce la respuesta aunque la contraseña sea una palabra de la página', function () {
        $this->principal->update(['contrasena' => 'crea_backup']);

        app(Sincronizador::class)->probar($this->principal->refresh());

        Http::assertSentCount(1);
    });

    it('una copia que llega dañada a la hora programada se vuelve a pedir pronto, una sola vez', function () {
        ($this->servir)('<html>otra copia pisó esta</html>');

        ($this->sincronizar)(OrigenEjecucion::Programada);
        $pronto = $this->principal->refresh()->proxima_en;
        expect($pronto->between(now()->addSeconds(25), now()->addSeconds(95)))->toBeTrue();

        ($this->sincronizar)(OrigenEjecucion::Programada);
        expect($this->principal->refresh()->proxima_en->greaterThan(now()->addMinutes(20)))->toBeTrue();
    });
});

describe('la proyección', function () {
    it('lee el nombre de la tarifa, el grupo y los bloqueos de calendario, y no anota cobros sin importe', function () {
        $copia = ConstructorCopia::nueva()->unidad('Cabaña 1')->cliente(1, 'Pérez', 'Ana')
            ->reserva(2026, 1, 'Cabaña 1', 1, '2026-11-10', '2026-11-13', ['tariffa' => 'Doble#@&1200', 'origine' => 'Grupo GRP-7', 'commento' => 'Llegan tarde [grupo GRP-7]'])
            ->reserva(2026, 2, 'Cabaña 1', 1, '2026-11-20', '2026-11-22', ['tariffa_tot' => '0', 'commento' => '[ical] Booking'])
            ->reserva(2026, 3, 'Cabaña 1', 1, '2026-11-25', '2026-11-27', ['tariffa_tot' => '900', 'commento' => 'Pidió el enlace [ical] de su reserva'])
            ->fila('soldi2026', ['idsoldi' => 2, 'motivazione' => '1;1;3;1', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '0', 'data_transazione' => '2026-10-01 09:30:00'])
            ->fila('soldi2026', ['idsoldi' => 3, 'motivazione' => '1;1;3;1', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '500', 'data_transazione' => '2026-10-02 23:30:00']);
        ($this->servir)($copia);
        ($this->sincronizar)();

        $primera = Reserva::query()->where('clave_origen', '2026:1')->sole();
        expect($primera->tarifa_nombre)->toBe('Doble')->and($primera->grupo_codigo)->toBe('GRP-7')->and($primera->tipo)->toBe(TipoReserva::Reserva)
            ->and(Reserva::query()->where('clave_origen', '2026:2')->sole()->tipo)->toBe(TipoReserva::Bloqueo)
            ->and(Reserva::query()->where('clave_origen', '2026:3')->sole()->tipo)->toBe(TipoReserva::Reserva);
        // El día del cobro es el que anotó la principal, en su hora.
        expect(Pago::query()->sole()->clave_origen)->toBe('soldi:2026:3')->and(Pago::query()->sole()->fecha->format('Y-m-d'))->toBe('2026-10-02');
    });

    it('una unidad que en la principal solo cambia de mayúsculas o acentos sigue siendo la misma, y sus reservas la encuentran', function () {
        ($this->servir)(($this->copia)());
        ($this->sincronizar)();
        $id = DB::table('unidades')->where('clave_origen', 'Cabaña 1')->value('id');

        $copia = ConstructorCopia::nueva()->unidad('CABANA 1', 4, 1)->unidad('Cabaña 2', 2, 2)
            ->cliente(1, 'Pérez', 'Ana')->cliente(2, 'Smith', 'John')
            ->reserva(2026, 1, 'cabaña 1', 1, '2026-11-10', '2026-11-13', ['tariffa_tot' => '3000.00'])
            ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14');
        ($this->servir)($copia);

        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(DB::table('unidades')->count())->toBe(2)
            ->and(DB::table('unidades')->where('id', $id)->value('codigo'))->toBe('CABANA 1')
            ->and(Reserva::query()->where('clave_origen', '2026:1')->sole()->unidad_id)->toBe($id)
            ->and(EventoReserva::query()->count())->toBe(0);
    });

    it('un comentario muy largo con acentos no detiene la copia, y dos cajas pueden intercambiar su nombre', function () {
        $con = fn (string $primera, string $segunda): ConstructorCopia => ($this->copia)()
            ->fila('casse', ['idcasse' => 1, 'nome_cassa' => $primera])->fila('casse', ['idcasse' => 2, 'nome_cassa' => $segunda])
            ->reserva(2026, 3, 'Cabaña 2', 2, '2026-12-01', '2026-12-03', ['commento' => str_repeat('ñandú ', 12000)]);
        ($this->servir)($con('Recepción', 'Banco'));
        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(strlen((string) Reserva::query()->where('clave_origen', '2026:3')->sole()->comentario))->toBeLessThanOrEqual(65535);

        ($this->servir)($con('Banco', 'Recepción'));
        expect(($this->sincronizar)()->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(DB::table('cajas')->orderBy('clave_origen')->pluck('nombre', 'clave_origen')->all())->toBe(['casse:1' => 'Banco', 'casse:2' => 'Recepción']);
    });

    it('una estancia que está en las tablas de dos años se proyecta una vez, con la clave del año en que empieza', function () {
        $copia = ConstructorCopia::nueva()->anios(2026, 2027)->unidad('Cabaña 1')->cliente(1, 'Pérez', 'Ana')
            // La misma en los dos años: la de 2027 dice de cuál viene.
            ->reserva(2026, 5, 'Cabaña 1', 1, '2027-01-02', '2027-01-05')
            ->reserva(2027, 1, 'Cabaña 1', 1, '2027-01-02', '2027-01-05', ['id_anni_prec' => 5])
            // Sin decirlo: misma unidad, fechas y titular.
            ->reserva(2026, 6, 'Cabaña 1', 1, '2027-01-10', '2027-01-12')
            ->reserva(2027, 2, 'Cabaña 1', 1, '2027-01-10', '2027-01-12')
            // La que cruza el fin de año empieza antes del calendario de 2027: ahí no tiene fechas.
            ->reserva(2026, 7, 'Cabaña 1', 1, '2026-12-30', '2027-01-02')
            ->reserva(2027, 3, 'Cabaña 1', 1, '2026-12-30', '2027-01-02', ['id_anni_prec' => 7])
            ->fila('soldi2027', ['idsoldi' => 2, 'motivazione' => '1;2;4;1', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '100', 'data_transazione' => '2027-01-02 10:00:00']);
        ($this->servir)($copia);

        $ejecucion = ($this->sincronizar)();

        expect(Reserva::query()->orderBy('clave_origen')->pluck('clave_origen')->all())->toBe(['2026:5', '2026:6', '2026:7'])
            ->and($ejecucion->incidencias)->toBe(['reservas_sin_fechas' => 1])
            ->and($ejecucion->filas['reservas_en_dos_anios'])->toBe(2)
            ->and(Pago::query()->sole()->reserva->clave_origen)->toBe('2026:5')
            ->and($this->principal->refresh()->anio_consulta)->toBe(2027);
    });
});

describe('de una generación del proyector a otra', function () {
    beforeEach(function () {
        ($this->servir)(($this->copia)());
        ($this->sincronizar)();
        // Como lo dejó un proyector anterior: su carga no dice de qué generación es y sus huellas son de otra fórmula.
        $this->envejecer = function (): void {
            EjecucionSincronizacion::query()->where('estado', EstadoEjecucion::Correcta->value)->get()
                ->each(fn (EjecucionSincronizacion $e) => $e->update(['filas' => array_diff_key((array) $e->filas, [Proyector::CUENTA_GENERACION => true])]));
            foreach (['unidades', 'huespedes', 'reservas'] as $tabla) {
                DB::table($tabla)->update(['huella_datos' => str_repeat('a', 64), 'huella_identidad' => str_repeat('b', 64), 'actualizado_en' => '2020-01-01 00:00:00']);
            }
        };
    });

    it('si nada cambió en la principal, solo repone las huellas: no toca las filas ni anuncia nada', function () {
        ($this->envejecer)();

        $ejecucion = ($this->sincronizar)();

        expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta)
            ->and(EventoReserva::query()->count())->toBe(0)
            ->and(Reserva::query()->count())->toBe(2)
            ->and(DB::table('reservas')->where('actualizado_en', '!=', '2020-01-01 00:00:00')->count())->toBe(0)
            ->and(DB::table('huespedes')->where('actualizado_en', '!=', '2020-01-01 00:00:00')->count())->toBe(0)
            ->and(DB::table('unidades')->where('actualizado_en', '!=', '2020-01-01 00:00:00')->count())->toBe(0)
            ->and(DB::table('reservas')->where('huella_datos', str_repeat('a', 64))->orWhere('huella_identidad', str_repeat('b', 64))->count())->toBe(0)
            ->and($ejecucion->filas[Proyector::CUENTA_GENERACION])->toBe(Proyector::GENERACION);
    });

    it('lo que sí cambió se pone al día sin eventos y sin crear la reserva otra vez; la siguiente ya los genera', function () {
        ($this->envejecer)();
        $ids = Reserva::query()->orderBy('id')->pluck('id', 'clave_origen')->all();
        $cambiada = fn (string $salida): ConstructorCopia => ConstructorCopia::nueva()->unidad('Cabaña 1', 4, 1)->unidad('Cabaña 2', 2, 2)
            ->cliente(1, 'Pérez', 'Ana')->cliente(2, 'Smith', 'John')
            ->reserva(2026, 1, 'Cabaña 1', 1, '2026-11-10', $salida, ['tariffa_tot' => '3000.00'])
            ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14')
            ->reserva(2026, 3, 'Cabaña 2', 2, '2026-12-01', '2026-12-03');

        ($this->servir)($cambiada('2026-11-15'));
        ($this->sincronizar)();

        expect(EventoReserva::query()->count())->toBe(0)
            ->and(Reserva::query()->count())->toBe(3)
            ->and(Reserva::query()->where('clave_origen', '2026:1')->sole()->id)->toBe($ids['2026:1'])
            ->and(Reserva::query()->where('clave_origen', '2026:1')->sole()->nochesOcupadas()->count())->toBe(5);

        ($this->servir)($cambiada('2026-11-16'));
        ($this->sincronizar)();

        expect(EventoReserva::query()->pluck('tipo')->map->value->all())->toBe(['modificada']);
    });
});
