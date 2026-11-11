<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\EstadoFuenteIcal;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoChoque;
use App\Enums\TipoReserva;
use App\Filament\Resources\Choques\Pages\ListChoques;
use App\Filament\Resources\FuentesIcal\Pages\CreateFuenteIcal;
use App\Ical\GestorIcal;
use App\Ical\LectorIcal;
use App\Models\Aviso;
use App\Models\BloqueoExterno;
use App\Models\Choque;
use App\Models\EnlacePublico;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Servicios\Calendario;
use App\Servicios\Disponibilidad;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function calendarioIcal(string ...$eventos): string
{
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Portal//ES\r\n".implode('', $eventos)."END:VCALENDAR\r\n";
}

function eventoIcal(string $uid, string $inicio, string $fin, string $resumen = 'Reserved'): string
{
    return "BEGIN:VEVENT\r\nUID:$uid\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;VALUE=DATE:$inicio\r\nDTEND;VALUE=DATE:$fin\r\nSUMMARY:$resumen\r\nEND:VEVENT\r\n";
}

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return str_contains($host, 'interno') ? ['10.0.0.8'] : ['93.184.216.34'];
        }
    });
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Nombre Privado']);
    $this->hoy = $this->propiedad->hoy();
    $this->fecha = fn (int $dias, string $formato = 'Y-m-d'): string => $this->hoy->addDays($dias)->format($formato);
    $this->reservar = fn (int $desde, int $hasta): Reserva => app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => ($this->fecha)($desde), 'salida' => ($this->fecha)($hasta),
        'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000,
    ]);
    $this->fuente = FuenteIcal::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Airbnb', 'url' => 'https://portal.example.test/secreto.ics']);
    $this->ical = fn (): GestorIcal => app(GestorIcal::class);
});

describe('leer iCal', function () {
    it('entiende fechas, repeticiones y eventos sin fin, y no guarda nombres de huéspedes', function () {
        $hoy = CarbonImmutable::parse('2030-06-01');
        $bloqueos = app(LectorIcal::class)->leer(calendarioIcal(
            eventoIcal('a', '20300610', '20300612', 'Airbnb (Not available)'),
            eventoIcal('b', '20300620', '20300622', 'Reserva de Juan Pérez +52 55 1234'),
            "BEGIN:VEVENT\r\nUID:c\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;VALUE=DATE:20300625\r\nSUMMARY:Closed\r\nEND:VEVENT\r\n",
            "BEGIN:VEVENT\r\nUID:d\r\nDTSTAMP:20260101T000000Z\r\nDTSTART:20300701T150000Z\r\nDTEND:20300703T110000Z\r\nSUMMARY:x\r\nEND:VEVENT\r\n",
            "BEGIN:VEVENT\r\nUID:e\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;VALUE=DATE:20300801\r\nDURATION:P2D\r\nSTATUS:CANCELLED\r\nEND:VEVENT\r\n",
            "BEGIN:VEVENT\r\nUID:f\r\nDTSTAMP:20260101T000000Z\r\nDTSTART;VALUE=DATE:20300901\r\nDTEND;VALUE=DATE:20300902\r\nRRULE:FREQ=WEEKLY;COUNT=2\r\nEND:VEVENT\r\n",
            eventoIcal('pasado', '20290101', '20290103'),
        ), $hoy);
        $porUid = collect($bloqueos)->keyBy(fn (array $b): string => explode('#', $b['uid'])[0].'#'.$b['llegada']);

        expect($porUid['a#2030-06-10'])->toMatchArray(['salida' => '2030-06-12', 'resumen' => 'Airbnb (Not available)'])
            ->and($porUid['b#2030-06-20']['resumen'])->toBeNull()
            ->and($porUid['c#2030-06-25']['salida'])->toBe('2030-06-26')
            ->and($porUid['d#2030-07-01']['salida'])->toBe('2030-07-03')
            ->and($porUid->has('e#2030-08-01'))->toBeFalse()
            ->and($porUid->has('f#2030-09-01'))->toBeTrue()
            ->and($porUid->has('f#2030-09-08'))->toBeTrue()
            ->and($porUid->has('pasado#2029-01-01'))->toBeFalse();
    });

    it('rechaza lo que no es un calendario', function () {
        expect(fn () => app(LectorIcal::class)->leer('<html>hola</html>', CarbonImmutable::now()))->toThrow(RuntimeException::class);
    });

    it('trae los bloqueos, los actualiza, quita los que desaparecen y deja de vender esas fechas', function () {
        Http::fake(['portal.example.test/*' => Http::sequence()
            ->push(calendarioIcal(eventoIcal('a', ($this->fecha)(2, 'Ymd'), ($this->fecha)(4, 'Ymd')), eventoIcal('b', ($this->fecha)(8, 'Ymd'), ($this->fecha)(9, 'Ymd'))))
            ->push(calendarioIcal(eventoIcal('a', ($this->fecha)(2, 'Ymd'), ($this->fecha)(5, 'Ymd'))))]);

        expect(($this->ical)()->leerFuente($this->fuente))->toBeTrue();
        expect($this->fuente->refresh()->estado)->toBe(EstadoFuenteIcal::Correcta)
            ->and(BloqueoExterno::query()->whereNull('eliminado_en')->count())->toBe(2)
            ->and(Disponibilidad::libres([$this->propiedad->id], ($this->fecha)(3), ($this->fecha)(4), 1))->toHaveCount(0)
            ->and(Disponibilidad::libres([$this->propiedad->id], ($this->fecha)(4), ($this->fecha)(5), 1))->toHaveCount(1);

        ($this->ical)()->leerFuente($this->fuente);
        expect(BloqueoExterno::query()->whereNull('eliminado_en')->count())->toBe(1)
            ->and(BloqueoExterno::query()->whereNull('eliminado_en')->first()->salida->format('Y-m-d'))->toBe(($this->fecha)(5))
            ->and(Disponibilidad::libres([$this->propiedad->id], ($this->fecha)(8), ($this->fecha)(9), 1))->toHaveCount(1);

        $mes = Calendario::mes([$this->propiedad->id], $this->hoy->addDays(3)->startOfMonth());
        expect($mes['filas'][0]['externos'][0]['fuente'] ?? null)->toBe('Airbnb');
    });

    it('si falla conserva lo que había, no enseña la dirección y avisa tras un rato', function () {
        Http::fake(['portal.example.test/*' => Http::sequence()
            ->push(calendarioIcal(eventoIcal('a', ($this->fecha)(2, 'Ymd'), ($this->fecha)(4, 'Ymd'))))
            ->push('Error', 500)->push('<html>', 200)->push('Error', 500)]);

        ($this->ical)()->leerFuente($this->fuente);
        expect(($this->ical)()->leerFuente($this->fuente))->toBeFalse();
        expect($this->fuente->refresh()->estado)->toBe(EstadoFuenteIcal::Error)
            ->and($this->fuente->ultimo_error)->not->toContain('secreto')
            ->and(BloqueoExterno::query()->whereNull('eliminado_en')->count())->toBe(1)
            ->and(Aviso::query()->where('tipo', 'ical_falla')->exists())->toBeFalse();

        ($this->ical)()->leerFuente($this->fuente);
        expect(BloqueoExterno::query()->whereNull('eliminado_en')->count())->toBe(1);

        $this->travel(4)->hours();
        ($this->ical)()->leerFuente($this->fuente);
        expect(Aviso::query()->where('tipo', 'ical_falla')->whereNotNull('clave_unica')->exists())->toBeTrue();
    });

    it('no se conecta a la red interna ni sigue redirecciones hacia ella', function () {
        Http::fake(['*' => Http::response('', 302, ['Location' => 'https://interno.example.test/x'])]);

        expect(($this->ical)()->leerFuente($this->fuente))->toBeFalse();
        Http::assertSentCount(1);

        $this->fuente->update(['url' => 'https://interno.example.test/x']);
        expect(($this->ical)()->leerFuente($this->fuente))->toBeFalse();
        Http::assertSentCount(1);
    });

    it('lee cada fuente solo cuando le toca', function () {
        Http::fake(['portal.example.test/*' => Http::response(calendarioIcal())]);

        expect(($this->ical)()->leerPendientes())->toBe(1)
            ->and(($this->ical)()->leerPendientes())->toBe(0);
        $this->travel(31)->minutes();
        expect(($this->ical)()->leerPendientes())->toBe(1);
    });
});

describe('exportar iCal', function () {
    it('solo enseña fechas, con un enlace difícil de adivinar y que se puede cambiar', function () {
        $reserva = ($this->reservar)(2, 4);
        app(GestorReservas::class)->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => ($this->fecha)(6), 'salida' => ($this->fecha)(7), 'comentario' => 'Pintura privada']);
        BloqueoExterno::query()->create(['fuente_id' => $this->fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'uid' => 'x', 'llegada' => ($this->fecha)(10), 'salida' => ($this->fecha)(11), 'primera_vez_en' => now(), 'visto_en' => now()]);

        $calendario = ($this->ical)()->calendarioDe($this->unidad);
        $token = $calendario->enlace->token();
        expect($token)->toMatch('/^[0-9a-f]{40}$/');
        expect(($this->ical)()->calendarioDe($this->unidad)->id)->toBe($calendario->id);

        $respuesta = $this->get(route('ical.exportar', ['token' => $token]))->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $texto = $respuesta->getContent();
        expect($texto)->toContain('DTSTART;VALUE=DATE:'.($this->fecha)(2, 'Ymd'))
            ->toContain('DTEND;VALUE=DATE:'.($this->fecha)(4, 'Ymd'))
            ->toContain('DTSTART;VALUE=DATE:'.($this->fecha)(6, 'Ymd'))
            ->toContain('DTSTART;VALUE=DATE:'.($this->fecha)(10, 'Ymd'))
            ->not->toContain('Nombre Privado')->not->toContain('Pintura privada')->not->toContain('reserva-'.$reserva->id);
        expect(EnlacePublico::query()->first()->usos)->toBe(1);

        $calendario->update(['incluir_bloqueos' => false]);
        expect($this->get(route('ical.exportar', ['token' => $token]))->getContent())->not->toContain('DTSTART;VALUE=DATE:'.($this->fecha)(6, 'Ymd'))->not->toContain('DTSTART;VALUE=DATE:'.($this->fecha)(10, 'Ymd'));

        $this->get(route('ical.exportar', ['token' => str_repeat('0', 40)]))->assertNotFound();

        ($this->ical)()->regenerarEnlaces($this->propiedad);
        $this->get(route('ical.exportar', ['token' => $token]))->assertNotFound();
        $this->get(route('ical.exportar', ['token' => $calendario->refresh()->enlace->token()]))->assertOk();
    });
});

describe('choques', function () {
    it('detecta un portal contra una reserva, avisa y se resuelve solo cuando deja de coincidir', function () {
        ($this->reservar)(2, 4);
        $bloqueo = BloqueoExterno::query()->create(['fuente_id' => $this->fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'uid' => 'x', 'llegada' => ($this->fecha)(3), 'salida' => ($this->fecha)(6), 'primera_vez_en' => now(), 'visto_en' => now()]);

        expect(($this->ical)()->revisarChoques($this->propiedad))->toBe(1)
            ->and(($this->ical)()->revisarChoques($this->propiedad))->toBe(1)
            ->and(Choque::query()->count())->toBe(1)
            ->and(Choque::query()->first()->tipo)->toBe(TipoChoque::BloqueoReserva)
            ->and(Choque::query()->first()->desde->format('Y-m-d'))->toBe(($this->fecha)(3))
            ->and(Choque::query()->first()->hasta->format('Y-m-d'))->toBe(($this->fecha)(4))
            ->and(Aviso::query()->where('tipo', 'choques')->whereNotNull('clave_unica')->exists())->toBeTrue();

        $bloqueo->update(['eliminado_en' => now()]);
        expect(($this->ical)()->revisarChoques($this->propiedad))->toBe(0)
            ->and(Choque::query()->first()->resuelto_en)->not->toBeNull()
            ->and(Aviso::query()->where('tipo', 'choques')->whereNotNull('clave_unica')->exists())->toBeFalse();

        // Si vuelve a coincidir, vuelve a avisar.
        $bloqueo->update(['eliminado_en' => null]);
        expect(($this->ical)()->revisarChoques($this->propiedad))->toBe(1)->and(Choque::query()->count())->toBe(2);
    });

    it('marcar uno resuelto no lo reabre', function () {
        ($this->reservar)(2, 4);
        BloqueoExterno::query()->create(['fuente_id' => $this->fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'uid' => 'x', 'llegada' => ($this->fecha)(3), 'salida' => ($this->fecha)(6), 'primera_vez_en' => now(), 'visto_en' => now()]);
        ($this->ical)()->revisarChoques($this->propiedad);
        $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

        Livewire::test(ListChoques::class)->callTableAction('resolver', Choque::query()->first(), ['nota' => 'Hablé con el portal'])->assertHasNoTableActionErrors();

        expect(($this->ical)()->revisarChoques($this->propiedad))->toBe(0)->and(Choque::query()->count())->toBe(1);
    });

    it('un bloqueo que ya creó la principal por el mismo evento no es un choque; dos reservas de una subordinada sí', function () {
        $sub = Propiedad::query()->create(['nombre' => 'Sub', 'codigo' => 'sub', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'UTC', 'moneda' => 'MXN'])->refresh();
        $unidad = Unidad::query()->forceCreate(['propiedad_id' => $sub->id, 'codigo' => '1', 'nombre' => 'Uno']);
        $fuente = FuenteIcal::query()->create(['propiedad_id' => $sub->id, 'unidad_id' => $unidad->id, 'nombre' => 'Portal', 'url' => 'https://portal.example.test/s.ics']);
        $crear = fn (string $numero, int $desde, int $hasta, TipoReserva $tipo = TipoReserva::Reserva): Reserva => Reserva::query()->forceCreate(['propiedad_id' => $sub->id, 'unidad_id' => $unidad->id, 'tipo' => $tipo, 'estado' => EstadoReserva::Confirmada, 'numero' => $numero, 'codigo_publico' => substr(md5($numero), 0, 10), 'llegada' => ($this->fecha)($desde), 'salida' => ($this->fecha)($hasta), 'moneda' => 'MXN']);
        $crear('b', 2, 4, TipoReserva::Bloqueo);
        BloqueoExterno::query()->create(['fuente_id' => $fuente->id, 'propiedad_id' => $sub->id, 'unidad_id' => $unidad->id, 'uid' => 'x', 'llegada' => ($this->fecha)(2), 'salida' => ($this->fecha)(4), 'primera_vez_en' => now(), 'visto_en' => now()]);
        expect(($this->ical)()->revisarChoques($sub))->toBe(0);

        $crear('r1', 10, 12);
        $crear('r2', 11, 13);
        expect(($this->ical)()->revisarChoques($sub))->toBe(1)
            ->and(Choque::query()->first()->tipo)->toBe(TipoChoque::ReservaReserva);
    });
});

it('el panel cifra la dirección y rechaza la red interna', function () {
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

    Livewire::test(CreateFuenteIcal::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Interno', 'url' => 'https://interno.example.test/x.ics', 'frecuencia_minutos' => 30])
        ->call('create')->assertHasFormErrors(['url']);

    Livewire::test(CreateFuenteIcal::class)
        ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Booking', 'url' => 'https://portal.example.test/nuevo-secreto.ics', 'frecuencia_minutos' => 30])
        ->call('create')->assertHasNoFormErrors();

    expect(FuenteIcal::query()->where('nombre', 'Booking')->first()->url)->toBe('https://portal.example.test/nuevo-secreto.ics')
        ->and((string) DB::table('fuentes_ical')->where('nombre', 'Booking')->value('url'))->not->toContain('nuevo-secreto');
});
