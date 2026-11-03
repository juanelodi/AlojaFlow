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
use App\Enums\OrigenEjecucion;
use App\Enums\TipoReserva;
use App\Models\Aviso;
use App\Models\EventoReserva;
use App\Models\Huesped;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
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
