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
use App\Filament\Resources\Huespedes\HuespedResource;
use App\Filament\Resources\Huespedes\Pages\ListHuespedes;
use App\Filament\Resources\Huespedes\Pages\ViewHuesped;
use App\Filament\Resources\Reservas\Pages\ListReservas;
use App\Filament\Resources\Reservas\ReservaResource;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Servicios\Buscador;
use App\Servicios\FichaHuesped;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\Column;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Once;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel('app');
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Bahía', 'codigo' => 'bahia', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'sur', 'nombre' => 'Cabaña Sur', 'capacidad' => 4]);
    $this->admin = User::query()->create(['name' => 'Ad', 'email' => 'ad@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true, 'es_administrador' => true]);
    $this->ana = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Rivas', 'nacionalidad_texto' => 'México', 'domicilio_ciudad' => 'Cancún', 'domicilio_region' => 'Quintana Roo', 'domicilio_pais_texto' => 'México']);
    $this->ana->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => '+52 998 111 2222', 'valor_normalizado' => '+529981112222']);
    $this->luis = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luis', 'apellido1' => 'Mora', 'nacionalidad_texto' => 'España', 'domicilio_ciudad' => 'Madrid', 'domicilio_region' => 'Madrid', 'domicilio_pais_texto' => 'España']);
    $this->reservar = function (Huesped $huesped, string $llegada, string $salida, array $extra = []): Reserva {
        static $numero = 100;

        return Reserva::query()->create($extra + [
            'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'titular_id' => $huesped->id, 'tipo' => TipoReserva::Reserva,
            'estado' => EstadoReserva::Confirmada, 'numero' => (string) ++$numero, 'codigo_publico' => 'C'.$numero, 'llegada' => $llegada, 'salida' => $salida,
            'personas' => 2, 'moneda' => 'MXN', 'total' => 100000, 'pagado' => 40000,
        ]);
    };
    $this->buscar = fn (string $texto): array => Buscador::reservas(Reserva::query(), $texto)->pluck('numero')->all();
});

describe('el buscador de reservas', function () {
    beforeEach(function () {
        $this->dic = ($this->reservar)($this->ana, '2026-12-15', '2026-12-18', ['origen_texto' => 'Booking', 'comentario' => 'Llega tarde', 'tarifa_nombre' => 'Temporada alta']);
        $this->ene = ($this->reservar)($this->luis, '2027-01-10', '2027-01-12', ['origen_texto' => 'Airbnb', 'estado' => EstadoReserva::SinConfirmar]);
    });

    it('encuentra por cualquier dato, sin elegir el campo', function (string $palabra) {
        expect(($this->buscar)($palabra))->toBe([$this->dic->numero]);
    })->with(['Rivas', 'cancun', 'Quintana', 'booking', 'tarde', 'Temporada', '998', '9981112', 'México']);

    it('con varias palabras, todas deben aparecer, en cualquier orden', function () {
        expect(($this->buscar)('ana cancún'))->toBe([$this->dic->numero])
            ->and(($this->buscar)('cancún ana'))->toBe([$this->dic->numero])
            ->and(($this->buscar)('rivas madrid'))->toBe([]);
    });

    it('también por el número, la unidad, el estado y la fecha', function () {
        expect(($this->buscar)($this->ene->numero))->toBe([$this->ene->numero])
            ->and(($this->buscar)('Cabaña'))->toHaveCount(2)
            ->and(($this->buscar)(__('enums.EstadoReserva.sin_confirmar')))->toBe([$this->ene->numero])
            ->and(($this->buscar)('15/12/2026'))->toBe([$this->dic->numero])
            ->and(($this->buscar)('18/12/26'))->toBe([$this->dic->numero])
            ->and(($this->buscar)('2027-01-10'))->toBe([$this->ene->numero])
            ->and(($this->buscar)('99/99/2026'))->toBe([]);
    });

    it('un comodín se busca como texto', function () {
        expect(($this->buscar)('%'))->toBe([])->and(($this->buscar)('_'))->toBe([]);
    });

    it('la lista lo usa, y solo sobre lo que se puede ver', function () {
        Livewire::actingAs($this->admin)->test(ListReservas::class)
            ->searchTable('cancún')
            ->assertCanSeeTableRecords([$this->dic])->assertCanNotSeeTableRecords([$this->ene]);
    });
});

describe('los filtros de reservas', function () {
    beforeEach(function () {
        $this->dic = ($this->reservar)($this->ana, '2026-12-15', '2026-12-18', ['origen_texto' => 'Booking']);
        $this->ene = ($this->reservar)($this->luis, '2027-01-10', '2027-01-12', ['origen_texto' => 'Airbnb', 'pagado' => 100000]);
        $this->lista = Livewire::actingAs($this->admin)->test(ListReservas::class);
    });

    it('por origen, nación, estado y ciudad', function (string $filtro, array $valores) {
        $this->lista->filterTable($filtro, $valores)
            ->assertCanSeeTableRecords([$this->dic])->assertCanNotSeeTableRecords([$this->ene]);
    })->with([
        'origen' => ['origen_texto', ['Booking']],
        'nación' => ['titular_nacionalidad_texto', ['México']],
        'país' => ['titular_domicilio_pais_texto', ['México']],
        'estado' => ['titular_domicilio_region', ['Quintana Roo']],
        'ciudad' => ['titular_domicilio_ciudad', ['Cancún']],
    ]);

    it('por saldo pendiente y por fecha de salida', function () {
        $this->lista->filterTable('con_saldo')->assertCanSeeTableRecords([$this->dic])->assertCanNotSeeTableRecords([$this->ene]);
        $this->lista->removeTableFilters()->filterTable('salida', ['desde' => '2027-01-01'])->assertCanSeeTableRecords([$this->ene])->assertCanNotSeeTableRecords([$this->dic]);
    });

    it('las opciones salen de lo que quien mira puede ver', function () {
        $otraPropiedad = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $unidad = Unidad::query()->create(['propiedad_id' => $otraPropiedad->id, 'codigo' => 'ajena', 'nombre' => 'Ajena', 'capacidad' => 2]);
        $ajeno = Huesped::query()->create(['propiedad_id' => $otraPropiedad->id, 'nombre' => 'Eva', 'domicilio_ciudad' => 'Ciudad secreta']);
        ($this->reservar)($ajeno, '2026-12-01', '2026-12-02', ['propiedad_id' => $otraPropiedad->id, 'unidad_id' => $unidad->id, 'origen_texto' => 'Canal secreto']);

        $recepcion = User::query()->create(['name' => 'Re', 'email' => 're@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true]);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        $tabla = Livewire::actingAs($recepcion)->test(ListReservas::class)->instance()->getTable();

        expect(array_keys($tabla->getFilter('origen_texto')->getOptions()))->toContain('Booking')->not->toContain('Canal secreto')
            ->and(array_keys($tabla->getFilter('titular_domicilio_ciudad')->getOptions()))->toContain('Cancún')->not->toContain('Ciudad secreta');
    });

    it('sin nada eliminado en una principal no ofrece ocultarlo', function () {
        expect(array_keys($this->lista->instance()->getTable()->getFilters()))->not->toContain('vigentes');
    });

    it('una propiedad que pasó a independiente sigue ocultando lo eliminado en la principal', function () {
        // La lista ya se abrió, sin nada que ocultar, y recuerda sus filtros: aun así no debe aparecer.
        $borrada = ($this->reservar)($this->luis, '2027-03-01', '2027-03-03', ['eliminada_en_principal_en' => now()]);
        Once::flush();

        $lista = Livewire::actingAs($this->admin)->test(ListReservas::class)
            ->assertCanSeeTableRecords([$this->dic])->assertCanNotSeeTableRecords([$borrada]);
        $lista->filterTable('vigentes', true)->assertCanSeeTableRecords([$this->dic, $borrada]);
        $lista->filterTable('vigentes', false)->assertCanSeeTableRecords([$borrada])->assertCanNotSeeTableRecords([$this->dic]);
    });
});

describe('las listas', function () {
    it('se paginan por 10, 20, 50 o 100', function () {
        $tabla = Livewire::actingAs($this->admin)->test(ListReservas::class)->instance()->getTable();

        expect($tabla->getPaginationPageOptions())->toBe([10, 20, 50, 100])
            ->and($tabla->getDefaultPaginationPageOption())->toBe(20);
    });

    it('en toda lista se puede ordenar por sus columnas de la base, sin error', function () {
        ($this->reservar)($this->ana, '2026-12-15', '2026-12-18');
        $revisadas = 0;
        $this->actingAs($this->admin);
        foreach (Filament::getPanel('app')->getResources() as $recurso) {
            $paginas = $recurso::getPages();
            $lista = isset($paginas['index']) ? $paginas['index']->getPage() : null;
            if ($lista === null || ! is_a($lista, ListRecords::class, true) || ! $recurso::canViewAny()) {
                continue;
            }
            $prueba = Livewire::test($lista);
            foreach ($prueba->instance()->getTable()->getColumns() as $columna) {
                if ($columna->isSortable()) {
                    $prueba->call('sortTable', $columna->getName())->assertOk();
                    $revisadas++;
                }
            }
        }
        expect($revisadas)->toBeGreaterThan(100);
    });

    it('lo calculado, lo cifrado y lo que viene de una lista no se ofrece para ordenar', function () {
        $columnas = fn (string $lista): array => collect(Livewire::actingAs($this->admin)->test($lista)->instance()->getTable()->getColumns())
            ->filter(fn (Column $columna): bool => $columna->isSortable())->keys()->all();

        expect($columnas(ListReservas::class))->toContain('numero', 'titular.apellido1', 'unidad.nombre', 'llegada', 'total', 'pendiente', 'origen_texto')->not->toContain('tarjeta')
            ->and($columnas(ListHuespedes::class))->toContain('id', 'apellido1', 'domicilio_ciudad', 'reservas_como_titular_count')->not->toContain('contactos.valor');
    });

    it('el código de cliente se ordena por lo que se ve', function () {
        $this->ana->forceFill(['sincronizada' => true, 'clave_origen' => '30'])->saveQuietly();
        $this->luis->forceFill(['sincronizada' => true, 'clave_origen' => '4'])->saveQuietly();

        Livewire::actingAs($this->admin)->test(ListHuespedes::class)
            ->call('sortTable', 'id', 'asc')
            ->assertCanSeeTableRecords([$this->luis, $this->ana], inOrder: true);
    });

    it('un campo obligatorio solo pierde la X si no es nativo', function () {
        expect(Select::make('a')->required()->native(false)->canSelectPlaceholder())->toBeFalse()
            ->and(Select::make('b')->native(false)->canSelectPlaceholder())->toBeTrue()
            ->and(Select::make('c')->required()->canSelectPlaceholder())->toBeTrue();
    });

    it('la fecha de las tablas no lleva relleno', function () {
        expect(Formato::fecha(CarbonImmutable::parse('2027-02-03')))->toBe('3 feb 2027')
            ->and(Formato::fechaCorta(CarbonImmutable::parse('2027-02-03')))->not->toContain('.');
    });
});

describe('la ficha del huésped', function () {
    it('muestra su código, sus cuentas y sus reservas por año', function () {
        $reserva = ($this->reservar)($this->ana, '2026-12-15', '2026-12-18');
        ($this->reservar)($this->ana, '2027-01-10', '2027-01-12');

        Livewire::actingAs($this->admin)->test(ViewHuesped::class, ['record' => $this->ana->id])
            ->assertSee($this->ana->codigo())->assertSee(__('ui.huespedes.codigo'))
            ->assertSee('2026')->assertSee('2027')->assertSee($reserva->numero)
            ->assertSee('af-ficha__mes', false)->assertDontSee('<details class="af-plegable af-ficha__anio" open', false)
            ->assertSee('tel:+529981112222', false);
    });

    it('el código es el de la principal si viene de allá', function () {
        $this->ana->forceFill(['sincronizada' => true, 'clave_origen' => '4711'])->saveQuietly();

        expect($this->ana->refresh()->codigo())->toBe('4711')->and($this->luis->codigo())->toBe((string) $this->luis->id);
    });

    it('cuenta noches y estancias, y el dinero solo de lo que pagó como titular', function () {
        ($this->reservar)($this->ana, '2026-12-15', '2026-12-18');
        ($this->reservar)($this->ana, '2027-01-10', '2027-01-12', ['estado' => EstadoReserva::Cancelada]);
        $ajena = ($this->reservar)($this->luis, '2027-02-01', '2027-02-05');
        $ajena->huespedes()->attach($this->ana->id);

        $cuentas = FichaHuesped::de($this->ana, $this->admin)['cuentas'];

        expect($cuentas['estancias'])->toBe(2)->and($cuentas['noches'])->toBe(7)
            ->and($cuentas['total'])->toBe(100000)->and($cuentas['pagado'])->toBe(40000)->and($cuentas['pendiente'])->toBe(60000);
    });

    it('quien solo ve lo suyo no cuenta las reservas de otros', function () {
        ($this->reservar)($this->ana, '2026-12-15', '2026-12-18');
        $vendedor = User::query()->create(['name' => 'Ve', 'email' => 've@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true]);
        Acceso::asignarRoles($vendedor, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);

        expect(FichaHuesped::de($this->ana, $vendedor->refresh())['cuentas']['estancias'])->toBe(0);
    });
});

describe('el buscador de arriba', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->dic = ($this->reservar)($this->ana, '2026-12-15', '2026-12-18', ['origen_texto' => 'Booking']);
    });

    it('encuentra una reserva por cualquier dato', function () {
        expect(ReservaResource::getGlobalSearchResults('cancún')->first()?->url)->toContain('/reservas/'.$this->dic->id)
            ->and(ReservaResource::getGlobalSearchResults('booking'))->toHaveCount(1)
            ->and(ReservaResource::getGlobalSearchResults('nada que ver'))->toHaveCount(0);
    });

    it('encuentra un huésped por teléfono, ciudad o código', function () {
        expect(HuespedResource::getGlobalSearchResults('9981112')->first()?->title)->toBe('Ana Rivas')
            ->and(HuespedResource::getGlobalSearchResults('madrid')->first()?->title)->toBe('Luis Mora')
            ->and(HuespedResource::getGlobalSearchResults((string) $this->ana->id)->first()?->title)->toBe('Ana Rivas');
    });

    it('no enseña lo que quien busca no puede ver', function () {
        $sinNada = User::query()->create(['name' => 'Na', 'email' => 'na@example.com', 'password' => 'una-clave-larga-de-prueba', 'activo' => true]);
        $this->actingAs($sinNada);

        expect(ReservaResource::getGlobalSearchResults('cancún'))->toHaveCount(0)
            ->and(HuespedResource::getGlobalSearchResults('madrid'))->toHaveCount(0);
    });
});

describe('la fecha y el estado de la ficha de la reserva', function () {
    it('la fecha con año sigue el orden de cada idioma', function () {
        expect(Formato::fechaCortaConAnio(CarbonImmutable::parse('2027-02-04')))->toBe('jue 4 feb 2027');
        app()->setLocale('en');
        expect(Formato::fechaCortaConAnio(CarbonImmutable::parse('2027-02-04')))->toBe('Thu Feb 4, 2027');
    });

    it('una reserva cancelada o eliminada no se presenta como una estancia', function () {
        $cancelada = ($this->reservar)($this->ana, '2026-12-15', '2026-12-18', ['estado' => EstadoReserva::Cancelada, 'salida_registrada_en' => now()]);
        $eliminada = ($this->reservar)($this->ana, '2027-01-15', '2027-01-18', ['eliminada_en_principal_en' => now(), 'entrada_registrada_en' => now()]);
        $this->actingAs($this->admin);

        $pinta = fn (Reserva $reserva): string => Blade::render('<x-estado-estancia :reserva="$reserva" />', ['reserva' => $reserva]);

        expect($pinta($cancelada))->toContain(EstadoReserva::Cancelada->etiqueta())->not->toContain(__('ui.comun.salio'))
            ->and($pinta($eliminada))->toContain(__('ui.reservas.eliminada'))->not->toContain(__('ui.comun.en_casa'));
    });
});
