<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Enums\TipoReserva;
use App\Filament\Pages\Calendario;
use App\Filament\Pages\Hoy;
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\BloqueoExterno;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Servicios\Calendario as ServicioCalendario;
use App\Support\Formato;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel('app');
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Bahía', 'codigo' => 'bahia', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->sur = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'sur', 'nombre' => 'Cabaña Sur', 'capacidad' => 4, 'orden' => 1]);
    $this->norte = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'norte', 'nombre' => 'Cabaña Norte', 'capacidad' => 2, 'orden' => 2]);
    $this->ana = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Rivas']);
    $this->ana->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => '+52 998 111 2222', 'valor_normalizado' => '+529981112222']);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = function (int $desde, int $hasta, array $extra = []): Reserva {
        static $numero = 500;

        return Reserva::query()->create($extra + [
            'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->sur->id, 'titular_id' => $this->ana->id, 'tipo' => TipoReserva::Reserva,
            'estado' => EstadoReserva::Confirmada, 'numero' => (string) ++$numero, 'codigo_publico' => 'P'.$numero,
            'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
            'personas' => 2, 'moneda' => 'MXN', 'total' => 100000, 'pagado' => 40000,
        ]);
    };
    $this->usuarioCon = function (string $rol, array $ajustes = []): User {
        $usuario = User::query()->create(['name' => $rol, 'email' => $rol.'@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true]);
        Acceso::asignarRoles($usuario, [['propiedad_id' => $this->propiedad->id, 'rol' => $rol] + $ajustes]);

        return $usuario;
    };
});

describe('Hoy en el teléfono', function (): void {
    it('da a recepción una tarjeta con lo esencial y sus botones', function (): void {
        $reserva = ($this->reservar)(0, 2);

        $this->actingAs(($this->usuarioCon)('recepcion'))->get(Hoy::getUrl())->assertOk()
            ->assertSee('af-lista-reservas', false)
            ->assertSee('af-tarjeta-reserva', false)
            ->assertSee('registrarEntrada('.$reserva->id.')', false)
            ->assertSee('tel:+529981112222', false)
            ->assertSee(Formato::dinero(60000, 'MXN'));
    });

    it('pasa de registrar la entrada a registrar la salida', function (): void {
        $reserva = ($this->reservar)(0, 2, ['entrada_registrada_en' => now()]);

        Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(Hoy::class)
            ->assertSee('registrarSalida('.$reserva->id.')', false)
            ->assertDontSee('registrarEntrada('.$reserva->id.')', false);
    });

    it('a quien limpia no le da teléfono, saldo, ficha ni botones', function (): void {
        $reserva = ($this->reservar)(0, 2);

        Livewire::actingAs(($this->usuarioCon)('limpieza'))->test(Hoy::class)
            ->assertOk()
            ->assertSee('Ana Rivas')
            ->assertDontSee('tel:', false)
            ->assertDontSee(Formato::dinero(60000, 'MXN'))
            ->assertDontSee('registrarEntrada('.$reserva->id.')', false);
    });
});

describe('el calendario por día', function (): void {
    it('dice qué pasa en cada unidad, con quien sale antes que quien llega', function (): void {
        $sale = ($this->reservar)(-2, 0, ['numero' => '701', 'codigo_publico' => 'S701']);
        $llega = ($this->reservar)(0, 2, ['numero' => '702', 'codigo_publico' => 'S702']);
        $this->actingAs(($this->usuarioCon)('recepcion'));

        $dia = ServicioCalendario::dia([$this->propiedad->id], $this->hoy, Acceso::usuarioActual());

        expect(array_map(fn (array $r): int => $r['reserva']->id, $dia['filas'][0]['reservas']))->toBe([$sale->id, $llega->id])
            ->and($dia['filas'][0]['reservas'][0]['momento'])->toBe('sale')
            ->and($dia['filas'][0]['reservas'][1]['momento'])->toBe('llega')
            ->and($dia['filas'][0]['libre'])->toBeFalse()
            ->and($dia['filas'][1]['libre'])->toBeTrue()
            ->and([$dia['libres'], $dia['ocupadas'], $dia['llegan'], $dia['salen']])->toBe([1, 1, 1, 1]);
    });

    it('da por libre esta noche la unidad de la que solo se sale', function (): void {
        ($this->reservar)(-2, 0);
        $this->actingAs(($this->usuarioCon)('recepcion'));

        expect(ServicioCalendario::dia([$this->propiedad->id], $this->hoy, Acceso::usuarioActual())['filas'][0]['libre'])->toBeTrue();
        Livewire::test(Calendario::class)->assertSee(__('ui.calendario.libre_noche'));
    });

    it('no da por libre lo que ocupa un calendario externo', function (): void {
        $fuente = FuenteIcal::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->norte->id, 'nombre' => 'Airbnb', 'url' => 'https://example.com/a.ics']);
        BloqueoExterno::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->norte->id, 'fuente_id' => $fuente->id, 'uid' => 'x1', 'llegada' => $this->hoy->format('Y-m-d'), 'salida' => $this->hoy->addDay()->format('Y-m-d'), 'primera_vez_en' => now(), 'visto_en' => now()]);
        $this->actingAs(($this->usuarioCon)('recepcion'));

        expect(ServicioCalendario::dia([$this->propiedad->id], $this->hoy, Acceso::usuarioActual())['filas'][1]['libre'])->toBeFalse();
        Livewire::test(Calendario::class)->assertSee(__('ical.calendario_externo', ['fuente' => 'Airbnb']));
    });

    it('ofrece una reserva nueva donde está libre, con las fechas puestas, a quien puede crearla', function (): void {
        $url = '?unidad='.$this->norte->id.'&amp;llegada='.$this->hoy->format('Y-m-d').'&amp;salida='.$this->hoy->addDay()->format('Y-m-d');

        Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(Calendario::class)->assertSee($url, false);
        Livewire::actingAs(($this->usuarioCon)('limpieza'))->test(Calendario::class)->assertOk()->assertDontSee($url, false);
    });

    it('a quien limpia le dice quién está, sin teléfono, saldo ni ficha', function (): void {
        ($this->reservar)(0, 2);

        Livewire::actingAs(($this->usuarioCon)('limpieza'))->test(Calendario::class, ['vista' => 'dia'])
            ->assertOk()->assertSee('Ana Rivas')->assertDontSee('tel:', false)->assertDontSee(Formato::dinero(60000, 'MXN'));
    });

    it('de lo ajeno solo dice que está ocupado, y no lo suma', function (): void {
        ($this->reservar)(0, 2);
        $usuario = ($this->usuarioCon)('recepcion', ['solo_propias' => true]);
        $this->actingAs($usuario);

        Livewire::test(Calendario::class, ['vista' => 'dia'])
            ->assertSee(__('permisos.limites.ocupada'))
            ->assertDontSee('Ana Rivas')
            ->assertDontSee('tel:', false)
            ->assertDontSee(__('ui.reservas.numero').' 5');
        expect(ServicioCalendario::dia([$this->propiedad->id], $this->hoy, $usuario)['llegan'])->toBe(0);
    });

    it('elegido el día, deja de armar el mes; y el mes, el día', function (): void {
        ($this->reservar)(0, 2);
        $recepcion = ($this->usuarioCon)('recepcion');

        Livewire::actingAs($recepcion)->test(Calendario::class)->assertSee('af-calendario', false)->assertSee('af-dia-lista', false);
        Livewire::actingAs($recepcion)->test(Calendario::class)->call('cambiarVista', 'dia')->assertSet('vista', 'dia')->assertSee('af-dia-lista', false)->assertDontSee('af-calendario"', false);
        Livewire::actingAs($recepcion)->test(Calendario::class)->call('cambiarVista', 'mes')->assertSet('vista', 'mes')->assertSee('af-calendario', false)->assertDontSee('af-dia-lista', false);
        Livewire::actingAs($recepcion)->test(Calendario::class)->call('cambiarVista', 'otra')->assertSet('vista', null);
    });

    it('al cambiar de día se lleva el mes, y al cambiar de mes abre en su primer día', function (): void {
        $pagina = Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(Calendario::class)
            ->set('dia', '2027-03-31')->call('moverDia', 1)
            ->assertSet('dia', '2027-04-01')->assertSet('mes', '2027-04');

        $pagina->call('moverMes', 1)->assertSet('mes', '2027-05')->assertSet('dia', '2027-05-01');
        $pagina->call('irAEsteMes')->assertSet('dia', $this->hoy->format('Y-m-d'));
        $pagina->set('dia', 'no-es-fecha')->assertOk()->assertSet('dia', $this->hoy->format('Y-m-d'));
    });

    it('en una subordinada es solo consulta', function (): void {
        $this->propiedad->update(['modo' => ModoPropiedad::Subordinada]);
        ($this->reservar)(0, 2, ['sincronizada' => true]);

        Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(Calendario::class)
            ->assertSee(__('ui.comun.solo_consulta'))
            ->assertDontSee('?unidad='.$this->norte->id, false);
    });
});

describe('la ficha de la reserva', function (): void {
    it('abre con el huésped, las fechas, el saldo y sus contactos, sin «Ver»', function (): void {
        $reserva = ($this->reservar)(0, 2);
        $recepcion = ($this->usuarioCon)('recepcion');

        $pagina = Livewire::actingAs($recepcion)->test(ViewReserva::class, ['record' => $reserva->id])->assertOk();

        expect($pagina->instance()->getHeading())->toBe('Ana Rivas')
            ->and($pagina->instance()->getSubheading())->toBe($pagina->instance()->getTitle())
            ->and($pagina->instance()->getTitle())->toContain((string) $reserva->numero)
            ->and($pagina->instance()->getBreadcrumb())->toBe($pagina->instance()->getTitle());
        $pagina->assertSee(Formato::fechaCortaConAnio($reserva->llegada))
            ->assertSee(Formato::dinero(60000, 'MXN'))
            ->assertSee('af-cifra--destacada', false)
            ->assertSee('tel:+529981112222', false)
            ->assertSee(HuespedResource::getUrl('view', ['record' => $this->ana]));
    });

    it('pone primero lo que toca hacer y lleva la limpieza a «Más»', function (): void {
        $reserva = ($this->reservar)(0, 2);

        Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(ViewReserva::class, ['record' => $reserva->id])
            ->assertActionVisible('registrarEntrada')
            ->assertSeeInOrder([__('ui.reservas.registrar_entrada'), __('mensajes.mandar.accion'), __('ui.reservas.mas_acciones')]);
    });

    it('un bloqueo no presume huésped ni dinero', function (): void {
        $bloqueo = ($this->reservar)(0, 2, ['tipo' => TipoReserva::Bloqueo, 'titular_id' => null, 'total' => 0, 'pagado' => 0]);

        $pagina = Livewire::actingAs(($this->usuarioCon)('recepcion'))->test(ViewReserva::class, ['record' => $bloqueo->id])->assertOk();

        expect($pagina->instance()->getSubheading())->toBeNull();
        $pagina->assertSee(__('ui.calendario.bloqueo'))->assertDontSee('af-cifra', false)->assertDontSee(__('ui.reservas.dinero'));
    });

    it('no la abre quien solo limpia', function (): void {
        $reserva = ($this->reservar)(0, 2);

        $this->actingAs(($this->usuarioCon)('limpieza'))->get(ReservaResource::getUrl('view', ['record' => $reserva]))->assertNotFound();
    });
});

describe('importes en las cifras', function (): void {
    it('un importe no se parte: pasa al renglón siguiente en lugar de cortarse', function (): void {
        $hoja = (string) file_get_contents(resource_path('css/alojaflow.css'));

        // Una columna fija no crece con su contenido, y «MX$174,033.33» salía en dos renglones o cortado en la orilla.
        expect($hoja)->toMatch('/\.af-cifras \{ display: flex; flex-wrap: wrap;/')
            ->toMatch('/\.af-kpis \{ display: flex; flex-wrap: wrap;/')
            ->toMatch('/\.af-cifra dd \{[^}]*white-space: nowrap;/')
            ->toMatch('/\.af-kpi dd \{[^}]*white-space: nowrap;/')
            ->not->toMatch('/\.af-cifra dd \{[^}]*overflow-wrap/');
    });

    it('la nota de un indicador sí puede ocupar varios renglones', function (): void {
        expect((string) file_get_contents(resource_path('css/alojaflow.css')))->toMatch('/\.af-kpi small \{[^}]*white-space: normal;/');
    });
});
