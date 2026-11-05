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
use App\Enums\OrigenEjecucion;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\Caja;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\Movimiento;
use App\Models\Pago;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Propiedades\Independizador;
use App\Sincronizacion\HotelDruid\ClientePrincipal;
use App\Sincronizacion\HotelDruid\Sincronizador;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConstructorCopia;

beforeEach(function () {
    PrincipalFingida::$registrada = false;
    // Sin DNS en las pruebas: la principal «resuelve» a una IP pública fija.
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return ['93.184.215.14'];
        }
    });

    $propiedad = Propiedad::query()->create([
        'nombre' => 'Cabañas del Lago', 'codigo' => 'lago', 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
    ]);
    $this->principal = $propiedad->principal()->create([
        'url_base' => 'https://principal.example/hoteldruid/', 'usuario' => 'admin', 'contrasena' => 'clave-secreta',
    ]);
});

/**
 * Copia que sirve la principal simulada; cada prueba la cambia entre sincronizaciones.
 */
final class PrincipalFingida
{
    public static ConstructorCopia|string $copia = '';

    public static bool $registrada = false;
}

/**
 * Principal simulada que responde como crea_backup.php.
 */
function fingirPrincipal(ConstructorCopia|string $copia): void
{
    PrincipalFingida::$copia = $copia;
    if (PrincipalFingida::$registrada) {
        return;
    }
    PrincipalFingida::$registrada = true;

    Http::fake(function (Request $peticion) {
        $datos = $peticion->data();
        if (($datos['crea_backup'] ?? null) === 'SI') {
            return Http::response("<html>File created.<br>\n<!-- Backup_created --></html>");
        }
        if (($datos['salva_backup'] ?? null) === 'SI') {
            $copia = PrincipalFingida::$copia;

            return Http::response($copia instanceof ConstructorCopia ? $copia->gzip() : $copia, 200, ['Content-Type' => 'application/x-gzip']);
        }
        if (($datos['cancella_backup'] ?? null) === 'SI') {
            return Http::response('<html>ok</html>');
        }

        return Http::response('<html><form><button name="crea_backup" value="1"></button></form></html>');
    });
}

function copiaBase(): ConstructorCopia
{
    return ConstructorCopia::nueva()
        ->unidad('Cabaña 1', 4, 1)->unidad('Cabaña 2', 2, 2)
        ->cliente(1, 'Pérez', 'Ana', ['telefono' => '+52 55 1234 5678', 'email' => 'Ana@Example.com', 'documento' => 'X1234567'])
        ->cliente(2, 'Smith', 'John')
        ->cliente(3, 'Bloqueo iCal')
        ->fila('relclienti', ['idclienti' => 1, 'numero' => 1, 'tipo' => 'campo_pers', 'testo1' => 'Categoria', 'testo3' => 'VIP'])
        ->reserva(2026, 1, 'Cabaña 1', 1, '2026-11-10', '2026-11-13', ['tariffa_tot' => '3000.00', 'pagato' => '1000'])
        ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14', ['conferma' => 'N'])
        ->reserva(2026, 3, 'Cabaña 2', 3, '2026-12-01', '2026-12-05', ['tariffa_tot' => '0', 'origine' => 'iCal Airbnb', 'commento' => '[ical] Airbnb'])
        ->fila('rclientiprenota2026', ['idprenota' => 1, 'idclienti' => 2, 'num_ordine' => 1, 'parentela' => 1]);
}

function sincronizar(Principal $principal): App\Models\EjecucionSincronizacion
{
    return app(Sincronizador::class)->sincronizar($principal->refresh(), OrigenEjecucion::Manual);
}

it('sincroniza la copia y proyecta unidades, huéspedes, reservas y noches', function () {
    fingirPrincipal(copiaBase());

    $ejecucion = sincronizar($this->principal);

    expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta)
        ->and(Unidad::query()->count())->toBe(2)
        ->and(Huesped::query()->count())->toBe(3)
        ->and(Reserva::query()->count())->toBe(3)
        ->and(EventoReserva::query()->count())->toBe(0); // la primera carga no genera eventos

    $reserva = Reserva::query()->where('clave_origen', '2026:1')->firstOrFail();
    expect($reserva->llegada->format('Y-m-d'))->toBe('2026-11-10')
        ->and($reserva->salida->format('Y-m-d'))->toBe('2026-11-13')
        ->and($reserva->noches)->toBe(3)
        ->and($reserva->total)->toBe(300000)
        ->and($reserva->pendiente)->toBe(200000)
        ->and($reserva->estado)->toBe(EstadoReserva::Confirmada)
        ->and($reserva->numero)->toBe('1/2026')
        ->and($reserva->sincronizada)->toBeTrue()
        ->and($reserva->nochesOcupadas()->pluck('importe')->all())->toBe([100000, 100000, 100000])
        ->and($reserva->huespedes()->count())->toBe(1);

    expect(Reserva::query()->where('clave_origen', '2026:2')->value('estado'))->toBe(EstadoReserva::SinConfirmar)
        ->and(Reserva::query()->where('clave_origen', '2026:3')->firstOrFail()->tipo)->toBe(TipoReserva::Bloqueo);

    $ana = Huesped::query()->where('clave_origen', '1')->firstOrFail();
    expect($ana->documento_numero)->toBe('X1234567')
        ->and(DB::table('huespedes')->where('id', $ana->id)->value('documento_numero'))->not->toBe('X1234567')
        ->and($ana->contactos->pluck('valor_normalizado')->all())->toBe(['+525512345678', 'ana@example.com'])
        ->and($ana->campos()->value('valor'))->toBe('VIP');

    $principal = $this->principal->refresh();
    expect($principal->ejecucion_vigente_id)->toBe($ejecucion->id)
        ->and($principal->version_esquema)->toBe('3.081')
        ->and($principal->inicio_sesion_activado)->toBeTrue()
        ->and($principal->anio_consulta)->toBe(2026)
        ->and(DB::table('hd_filas')->where('tabla', 'utenti')->count())->toBe(0);
});

it('solo envía parámetros de la lista blanca, por POST y a crea_backup.php', function () {
    fingirPrincipal(copiaBase());

    sincronizar($this->principal);

    Http::assertSentCount(3);
    Http::assertSent(function (Request $peticion): bool {
        return $peticion->method() === 'POST'
            && $peticion->url() === 'https://principal.example/hoteldruid/'.ClientePrincipal::ARCHIVO
            && ! str_contains($peticion->url(), 'clave-secreta')
            && array_diff(array_keys($peticion->data()), ClientePrincipal::PARAMETROS_PERMITIDOS) === [];
    });
});

it('detecta cambios en la segunda sincronización y genera eventos', function () {
    fingirPrincipal(copiaBase());
    sincronizar($this->principal);

    $cambiada = ConstructorCopia::nueva()
        ->unidad('Cabaña 1', 4, 1)->unidad('Cabaña 2', 2, 2)
        ->cliente(1, 'Pérez', 'Ana')->cliente(2, 'Smith', 'John')->cliente(4, 'Ruiz', 'Eva')
        ->reserva(2026, 1, 'Cabaña 1', 1, '2026-11-10', '2026-11-15', ['tariffa_tot' => '5000.00'])  // modificada
        ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14', [], cancelada: true)            // cancelada
        ->reserva(2026, 4, 'Cabaña 2', 4, '2026-11-20', '2026-11-22');                               // nueva; la 3 desaparece
    fingirPrincipal($cambiada);
    $ejecucion = sincronizar($this->principal);

    expect($ejecucion->estado)->toBe(EstadoEjecucion::Correcta)
        ->and(EventoReserva::query()->orderBy('id')->pluck('tipo')->map->value->sort()->values()->all())
        ->toBe(['cancelada', 'creada', 'eliminada', 'modificada']);

    $primera = Reserva::query()->where('clave_origen', '2026:1')->firstOrFail();
    expect($primera->noches)->toBe(5)->and($primera->nochesOcupadas()->count())->toBe(5);
    expect(Reserva::query()->where('clave_origen', '2026:2')->firstOrFail()->nochesOcupadas()->count())->toBe(0)
        ->and(Reserva::query()->where('clave_origen', '2026:3')->value('eliminada_en_principal_en'))->not->toBeNull();
});

it('trata un número de reserva reutilizado como una reserva nueva', function () {
    fingirPrincipal(copiaBase());
    sincronizar($this->principal);
    $vieja = Reserva::query()->where('clave_origen', '2026:1')->firstOrFail();

    // La reserva 1 se borró en la principal y su número se usó para otra (otra fecha de alta).
    $copia = ConstructorCopia::nueva()->unidad('Cabaña 1', 4, 1)->unidad('Cabaña 2', 2, 2)
        ->cliente(1, 'Pérez', 'Ana')->cliente(2, 'Smith', 'John')->cliente(3, 'Bloqueo iCal')
        ->reserva(2026, 1, 'Cabaña 1', 2, '2026-12-20', '2026-12-22', ['datainserimento' => '2026-10-30 12:00:00'])
        ->reserva(2026, 2, 'Cabaña 2', 2, '2026-11-12', '2026-11-14', ['conferma' => 'N'])
        ->reserva(2026, 3, 'Cabaña 2', 3, '2026-12-01', '2026-12-05', ['tariffa_tot' => '0', 'origine' => 'iCal Airbnb', 'commento' => '[ical] Airbnb']);
    fingirPrincipal($copia);
    sincronizar($this->principal);

    $vieja->refresh();
    expect($vieja->clave_origen)->toBeNull()
        ->and($vieja->eliminada_en_principal_en)->not->toBeNull()
        ->and(Reserva::query()->where('clave_origen', '2026:1')->firstOrFail()->id)->not->toBe($vieja->id);
});

it('pausa la principal sin reintentar si rechaza las credenciales', function () {
    Http::fake(['*' => Http::response('<form><input type="password" name="password_phpr"></form>')]);

    $ejecucion = sincronizar($this->principal);
    $principal = $this->principal->refresh();

    expect($ejecucion->estado)->toBe(EstadoEjecucion::Fallida)
        ->and($ejecucion->codigo_error)->toBe('SUB-003')
        ->and($principal->estado)->toBe(EstadoPrincipal::Pausada)
        ->and($principal->toca(now()->addDay()))->toBeFalse()
        ->and(Aviso::query()->whereNull('resuelto_en')->where('tipo', 'sincronizacion_fallida')->exists())->toBeTrue();
    Http::assertSentCount(1); // ni descarga ni borrado
});

it('abre un aviso si la principal no tiene activado el inicio de sesión', function () {
    fingirPrincipal(copiaBase()->sinInicioSesion());

    sincronizar($this->principal);

    expect(Aviso::query()->where('tipo', 'principal_sin_inicio_sesion')->whereNull('resuelto_en')->exists())->toBeTrue();
});

it('conserva la última carga buena si la copia llega dañada', function () {
    fingirPrincipal(copiaBase());
    sincronizar($this->principal);
    $vigente = $this->principal->refresh()->ejecucion_vigente_id;

    fingirPrincipal(gzencode('<html>no es una copia</html>'));
    $ejecucion = sincronizar($this->principal);

    expect($ejecucion->estado)->toBe(EstadoEjecucion::Fallida)
        ->and($this->principal->refresh()->ejecucion_vigente_id)->toBe($vigente)
        ->and(Reserva::query()->whereNull('eliminada_en_principal_en')->count())->toBe(3);
});

it('la sincronización solo escribe en el espejo, la proyección y sus registros', function () {
    fingirPrincipal(copiaBase());
    $tablas = [];
    DB::listen(function ($consulta) use (&$tablas): void {
        if (preg_match('/^\s*(insert into|update|delete from)\s+`?(\w+)`?/i', $consulta->sql, $m)) {
            $tablas[strtolower($m[2])] = true;
        }
    });

    sincronizar($this->principal);

    $permitidas = [
        'hd_filas', 'unidades', 'huespedes', 'contactos_huesped', 'campos_huesped', 'reservas', 'reservas_noches',
        'reservas_huespedes', 'eventos_reserva', 'principales', 'ejecuciones_sincronizacion', 'avisos', 'cache', 'cache_locks',
    ];
    expect(array_diff(array_keys($tablas), $permitidas))->toBe([]);
});

it('impide cambiar desde la aplicación una fila sincronizada', function () {
    fingirPrincipal(copiaBase());
    sincronizar($this->principal);

    $reserva = Reserva::query()->firstOrFail();

    expect(fn () => $reserva->update(['comentario' => 'cambio']))->toThrow(LogicException::class);
});

describe('la caja de la principal', function () {
    beforeEach(function () {
        $this->copia = copiaBase()
            ->fila('casse', ['idcasse' => 1, 'nome_cassa' => 'Recepción'])
            ->fila('casse', ['idcasse' => 2, 'nome_cassa' => 'Banco'])
            // Dos cobros de la reserva 1 y uno de una que no existe; la primera fila de cada año no es de una reserva.
            ->fila('soldi2026', ['idsoldi' => 1, 'motivazione' => 'soldi_prenotazioni_cancellate', 'saldo_prenota' => ''])
            ->fila('soldi2026', ['idsoldi' => 2, 'motivazione' => '1;10;13;1', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '700.00', 'data_transazione' => '2026-10-01 09:30:00'])
            ->fila('soldi2026', ['idsoldi' => 3, 'motivazione' => '1;10;13;1', 'metodo_pagamento' => 'PayPal', 'saldo_prenota' => '300.00', 'data_transazione' => '2026-10-02 10:00:00'])
            ->fila('soldi2026', ['idsoldi' => 4, 'motivazione' => '9;10;13;99', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '50.00', 'data_transazione' => '2026-10-03 10:00:00'])
            // El cobro de 700 entró a la caja: su concepto lleva el apellido, que no debe guardarse.
            ->fila('costi2026', ['idcosti' => 1, 'nome_costo' => 'Prenotazione 1 dal 10 al 13 per Pérez', 'val_costo' => '700.00', 'tipo_costo' => 'e', 'nome_cassa' => 'Recepción', 'provenienza_costo' => 'p', 'metodo_pagamento' => 'Efectivo', 'data_transazione' => '2026-10-01 09:30:00'])
            // Otro cobro anotado en la caja que ya no está en soldi: no debe detener la copia.
            ->fila('costi2026', ['idcosti' => 4, 'nome_costo' => 'Prenotazione 2 dal 12 al 14 per Smith', 'val_costo' => '99.00', 'tipo_costo' => 'e', 'nome_cassa' => 'Recepción', 'provenienza_costo' => 'p', 'metodo_pagamento' => 'Efectivo', 'data_transazione' => '2026-10-01 09:30:00'])
            ->fila('costi2026', ['idcosti' => 2, 'nome_costo' => 'Compra de garrafones', 'val_costo' => '120.50', 'tipo_costo' => 's', 'nome_cassa' => 'Recepción', 'provenienza_costo' => 'u', 'metodo_pagamento' => 'contanti', 'data_transazione' => '2026-10-05 12:00:00'])
            ->fila('costi2026', ['idcosti' => 3, 'nome_costo' => 'Renta de salón', 'val_costo' => '1000', 'tipo_costo' => 'e', 'nome_cassa' => 'Banco', 'provenienza_costo' => 'u', 'metodo_pagamento' => 'bonifico', 'data_transazione' => '2026-10-06 12:00:00']);
        fingirPrincipal($this->copia);
        expect(sincronizar($this->principal)->estado)->toBe(EstadoEjecucion::Correcta);
    });

    it('trae cajas, cobros y movimientos sin contar dos veces lo que viene de una reserva', function () {
        $propiedad = $this->principal->propiedad_id;
        expect(Caja::query()->where('propiedad_id', $propiedad)->orderBy('nombre')->pluck('nombre')->all())->toBe(['Banco', 'Recepción']);

        $pagos = Pago::query()->where('propiedad_id', $propiedad)->orderBy('fecha')->get();
        expect($pagos)->toHaveCount(2)
            ->and($pagos[0]->importe)->toBe(70000)->and($pagos[0]->metodo)->toBe(MetodoPago::Efectivo)->and($pagos[0]->fecha->format('Y-m-d'))->toBe('2026-10-01')
            ->and($pagos[0]->caja_id)->toBe(Caja::query()->where('nombre', 'Recepción')->value('id'))
            ->and($pagos[1]->metodo)->toBe(MetodoPago::Otro)->and($pagos[1]->referencia)->toBe('PayPal')->and($pagos[1]->caja_id)->toBeNull()
            ->and($pagos[0]->reserva->clave_origen)->toBe('2026:1');

        $movimientos = Movimiento::query()->where('propiedad_id', $propiedad)->orderBy('fecha')->get();
        expect($movimientos)->toHaveCount(2)
            ->and($movimientos[0]->tipo)->toBe(TipoMovimiento::Gasto)->and($movimientos[0]->importe)->toBe(12050)->and($movimientos[0]->metodo)->toBe(MetodoPago::Efectivo)
            ->and($movimientos[1]->tipo)->toBe(TipoMovimiento::Ingreso)->and($movimientos[1]->metodo)->toBe(MetodoPago::Transferencia);
        expect((string) json_encode($movimientos->pluck('concepto')))->not->toContain('Pérez');
    });

    it('una caja puede tomar el nombre de otra que se quitó', function () {
        fingirPrincipal(copiaBase()->fila('casse', ['idcasse' => 1, 'nome_cassa' => 'Banco']));
        expect(sincronizar($this->principal->refresh())->estado)->toBe(EstadoEjecucion::Correcta);
        expect(Caja::query()->where('propiedad_id', $this->principal->propiedad_id)->sole()->clave_origen)->toBe('casse:1');
    });

    it('otra sincronización no duplica ni reescribe, y lo que quita la principal se va', function () {
        $propiedad = $this->principal->propiedad_id;
        $id = Pago::query()->where('propiedad_id', $propiedad)->orderBy('id')->value('id');
        $actualizado = '2020-01-01 00:00:00';
        DB::table('pagos')->where('id', $id)->update(['actualizado_en' => $actualizado]);

        expect(sincronizar($this->principal->refresh())->estado)->toBe(EstadoEjecucion::Correcta);
        expect(Pago::query()->where('propiedad_id', $propiedad)->count())->toBe(2)
            ->and(DB::table('pagos')->where('id', $id)->value('actualizado_en'))->toBe($actualizado);

        fingirPrincipal(copiaBase()->fila('soldi2026', ['idsoldi' => 2, 'motivazione' => '1;10;13;1', 'metodo_pagamento' => 'Efectivo', 'saldo_prenota' => '800.00', 'data_transazione' => '2026-10-01 09:30:00']));
        expect(sincronizar($this->principal->refresh())->estado)->toBe(EstadoEjecucion::Correcta);
        expect(Pago::query()->where('propiedad_id', $propiedad)->sole()->importe)->toBe(80000)
            ->and(Movimiento::query()->where('propiedad_id', $propiedad)->count())->toBe(0)
            ->and(Caja::query()->where('propiedad_id', $propiedad)->count())->toBe(0);
    });

    it('al pasar a independiente, todo eso queda como propio y lo pagado cuadra', function () {
        $propiedad = Propiedad::query()->findOrFail($this->principal->propiedad_id);
        $reserva = Reserva::query()->where('clave_origen', '2026:1')->firstOrFail();
        expect($reserva->pagado)->toBe(100000);

        app(Independizador::class)->independizar($propiedad);

        expect(Pago::query()->where('propiedad_id', $propiedad->id)->where('sincronizada', true)->count())->toBe(0)
            ->and(Movimiento::query()->where('propiedad_id', $propiedad->id)->where('sincronizada', true)->count())->toBe(0)
            ->and((int) Pago::query()->where('reserva_id', $reserva->id)->sum('importe'))->toBe(100000);
    });
});
