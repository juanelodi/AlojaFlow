<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\BaseCargo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Filament\Pages\Calendario;
use App\Filament\Resources\Unidades\Pages\EditUnidad;
use App\Models\ConceptoCargo;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use App\Publico\CalendarioPublico;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ReglasCargo;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Disponibilidad;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'calendario_publico' => true]],
    ]);
    $this->propiedad->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 4]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'orden' => 1]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->gestor = app(GestorReservas::class);
    $this->reservar = fn (array $mas = []): Reserva => $this->gestor->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-03-10', 'salida' => '2027-03-13', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 300000,
    ]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
});

afterEach(fn () => Ajustes::olvidar());

describe('cambiar fechas arrastrando', function (): void {
    it('soltar en el borde derecho cambia la salida: el día es la última noche', function (): void {
        $reserva = ($this->reservar)();

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $reserva->id, 'fin', '2027-03-14');

        $reserva->refresh();
        expect($reserva->salida->format('Y-m-d'))->toBe('2027-03-15')
            ->and($reserva->llegada->format('Y-m-d'))->toBe('2027-03-10')
            ->and($reserva->noches)->toBe(5)
            ->and($reserva->nochesOcupadas()->count())->toBe(5);
    });

    it('soltar en el borde izquierdo cambia la llegada', function (): void {
        $reserva = ($this->reservar)();

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $reserva->id, 'inicio', '2027-03-08');

        $reserva->refresh();
        expect($reserva->llegada->format('Y-m-d'))->toBe('2027-03-08')
            ->and($reserva->salida->format('Y-m-d'))->toBe('2027-03-13');
    });

    it('el total se queda, y si la tarifa dice otro, lo pregunta', function (): void {
        $reserva = ($this->reservar)();

        Livewire::actingAs($this->gerente)->test(Calendario::class)
            ->call('cambiarFechas', $reserva->id, 'fin', '2027-03-14')
            ->assertNotified();

        expect($reserva->refresh()->total)->toBe(300000)
            ->and($this->gestor->totalSegunTarifa($reserva))->toBe(500000);
    });

    it('el botón aplica la tarifa, con sus cargos, su descuento y lo que no es del catálogo', function (): void {
        $cargo = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorNoche, 'importe' => 10000]);
        $reserva = ($this->reservar)(['cargos' => [['concepto_cargo_id' => $cargo->id, 'cantidad' => 1]], 'descuento' => 5000, 'comentario' => 'Se queda', 'origen_texto' => 'Directo', 'total' => 325000]);
        $this->gestor->agregarCargo($reserva, 'Lo que marcó el medidor', 1, 7000);
        $this->gestor->cambiarFechas($reserva->refresh(), CarbonImmutable::parse('2027-03-10'), CarbonImmutable::parse('2027-03-15'));

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('aplicarTarifa', $reserva->id)->assertNotified(__('ui.calendario.total_aplicado'));

        $reserva->refresh();
        // Cinco noches, menos el descuento, más cinco desayunos, más el medidor.
        expect($reserva->total)->toBe(500000 - 5000 + 50000 + 7000)
            ->and($reserva->cargos()->where('nombre', 'Desayuno')->sole()->importe)->toBe(50000)
            ->and($reserva->cargos()->whereNull('concepto_cargo_id')->sole()->importe)->toBe(7000)
            ->and($reserva->descuento)->toBe(5000)
            ->and($reserva->comentario)->toBe('Se queda')
            ->and($reserva->origen_texto)->toBe('Directo')
            ->and($reserva->llegada->format('Y-m-d'))->toBe('2027-03-10')
            ->and($reserva->salida->format('Y-m-d'))->toBe('2027-03-15');
    });

    it('no pisa otra reserva', function (): void {
        $reserva = ($this->reservar)();
        ($this->reservar)(['llegada' => '2027-03-13', 'salida' => '2027-03-15']);

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $reserva->id, 'fin', '2027-03-13')->assertNotified();

        expect($reserva->refresh()->salida->format('Y-m-d'))->toBe('2027-03-13');
    });

    it('la salida no puede quedar antes de la llegada', function (): void {
        $reserva = ($this->reservar)();

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $reserva->id, 'inicio', '2027-03-13');
        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $reserva->id, 'fin', '2027-03-08');

        $reserva->refresh();
        expect($reserva->llegada->format('Y-m-d'))->toBe('2027-03-10')
            ->and($reserva->salida->format('Y-m-d'))->toBe('2027-03-13');
    });

    it('a quien ya llegó solo se le mueve la salida, y a quien ya salió, nada', function (): void {
        $reserva = ($this->reservar)();
        $reserva->forceFill(['entrada_registrada_en' => now('UTC')])->save();

        expect(fn () => $this->gestor->cambiarFechas($reserva, CarbonImmutable::parse('2027-03-09'), CarbonImmutable::parse('2027-03-13')))->toThrow(ReservaRechazada::class);
        $this->gestor->cambiarFechas($reserva->refresh(), CarbonImmutable::parse('2027-03-10'), CarbonImmutable::parse('2027-03-14'));
        expect($reserva->refresh()->salida->format('Y-m-d'))->toBe('2027-03-14');

        $reserva->forceFill(['salida_registrada_en' => now('UTC')])->save();
        expect(fn () => $this->gestor->cambiarFechas($reserva, CarbonImmutable::parse('2027-03-10'), CarbonImmutable::parse('2027-03-15')))->toThrow(ReservaRechazada::class);
    });

    it('lo de un grupo se cambia desde el grupo', function (): void {
        $reserva = ($this->reservar)();
        $reserva->forceFill(['grupo_id' => null])->save();
        $grupo = Grupo::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'G1', 'nombre' => 'Boda', 'llegada' => '2027-03-10', 'salida' => '2027-03-13']);
        $reserva->forceFill(['grupo_id' => $grupo->id])->save();

        expect(fn () => $this->gestor->cambiarFechas($reserva->refresh(), CarbonImmutable::parse('2027-03-10'), CarbonImmutable::parse('2027-03-15')))->toThrow(ReservaRechazada::class)
            ->and($this->gestor->aplicarTarifa($reserva->refresh())->total)->toBe(300000);
    });

    it('un bloqueo también se estira, sin hablar de precios', function (): void {
        $bloqueo = $this->gestor->crear($this->propiedad, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $this->unidad->id, 'llegada' => '2027-04-01', 'salida' => '2027-04-03']);

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $bloqueo->id, 'fin', '2027-04-04')->assertNotified(__('ui.calendario.fechas_cambiadas'));

        expect($bloqueo->refresh()->salida->format('Y-m-d'))->toBe('2027-04-05');
    });

    it('la barra lleva sus extremos, y lo de un recepcionista con límite no se cambia', function (): void {
        ($this->reservar)(['llegada' => $this->propiedad->hoy()->addDays(2)->format('Y-m-d'), 'salida' => $this->propiedad->hoy()->addDays(4)->format('Y-m-d')]);

        Livewire::actingAs($this->gerente)->test(Calendario::class)->assertSeeHtml('data-asa="inicio"')->assertSeeHtml('data-asa="fin"');
    });

    it('otra propiedad no se toca', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $unidad = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'X', 'capacidad' => 2]);
        $ajena = app(GestorReservas::class)->crear($otra, ['tipo' => TipoReserva::Bloqueo->value, 'unidad_id' => $unidad->id, 'llegada' => '2027-03-10', 'salida' => '2027-03-13']);

        Livewire::actingAs($this->gerente)->test(Calendario::class)->call('cambiarFechas', $ajena->id, 'fin', '2027-03-20')->assertNotFound();
    });
});

describe('camas extra', function (): void {
    beforeEach(function (): void {
        $this->unidad->update(['camas_extra' => 1]);
        $this->cama = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Cama extra', 'base' => BaseCargo::PorNoche, 'importe' => 30000, 'obligatorio' => true, 'por_cama_extra' => true]);
    });

    it('el tope es la capacidad más las camas', function (): void {
        expect($this->unidad->refresh()->capacidadMaxima())->toBe(3);

        expect(($this->reservar)(['personas' => 3])->personas)->toBe(3)
            ->and(fn () => ($this->reservar)(['llegada' => '2027-05-01', 'salida' => '2027-05-03', 'personas' => 4]))->toThrow(ReservaRechazada::class);
    });

    it('sin camas extra, el tope es el de siempre', function (): void {
        $this->unidad->update(['camas_extra' => 0]);

        expect(fn () => ($this->reservar)(['personas' => 3]))->toThrow(ReservaRechazada::class);
    });

    it('cambiar personas o mover a otra unidad también cuentan con ellas', function (): void {
        $reserva = ($this->reservar)();
        expect($this->gestor->cambiarPersonas($reserva, 3)->personas)->toBe(3);

        $chica = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2]);
        expect(fn () => $this->gestor->mover($reserva->refresh(), $chica->id, CarbonImmutable::parse('2027-03-10')))->toThrow(ReservaRechazada::class);

        $chica->update(['camas_extra' => 1]);
        expect($this->gestor->mover($reserva->refresh(), $chica->id, CarbonImmutable::parse('2027-03-10'))->unidad_id)->toBe($chica->id);
    });

    it('la disponibilidad la encuentra para esas personas', function (): void {
        expect(Disponibilidad::libres([$this->propiedad->id], '2027-03-01', '2027-03-03', 3)->pluck('id')->all())->toBe([$this->unidad->id])
            ->and(Disponibilidad::libres([$this->propiedad->id], '2027-03-01', '2027-03-03', 4)->count())->toBe(0);
    });

    it('el cargo se suma solo, una vez por cada cama que se use', function (): void {
        $this->unidad->update(['camas_extra' => 2]);
        $llegada = CarbonImmutable::parse('2027-03-01');
        $salida = CarbonImmutable::parse('2027-03-03');
        $cargos = fn (int $personas): array => Cotizador::presupuesto($this->unidad->refresh(), $llegada, $salida, $personas)->cargos;

        expect($cargos(2))->toBe([])
            ->and($cargos(3)[0]['importe'])->toBe(60000)
            ->and($cargos(3)[0]['cantidad'])->toBe(1.0)
            ->and($cargos(4)[0]['importe'])->toBe(120000)
            ->and($cargos(4)[0]['cantidad'])->toBe(2.0)
            ->and(ReglasCargo::camasExtra($this->unidad, 4))->toBe(2);
    });

    it('el motor la ofrece, con su cargo en el precio y su tope', function (): void {
        $hoy = $this->propiedad->hoy();
        $ofertas = app(MotorReservas::class)->ofertas($this->propiedad, $hoy->addDays(5), $hoy->addDays(7), 3);

        expect($ofertas[0]->capacidad)->toBe(3)
            ->and($ofertas[0]->presupuesto->total())->toBe(200000 + 60000)
            ->and(app(MotorReservas::class)->ofertas($this->propiedad, $hoy->addDays(5), $hoy->addDays(7), 4))->toBe([]);
    });

    it('un cargo opcional por cama extra solo se ofrece si se usa', function (): void {
        $this->cama->update(['obligatorio' => false]);
        $hoy = $this->propiedad->hoy();
        $motor = app(MotorReservas::class);

        expect($motor->cargosOpcionales($this->propiedad, $this->unidad, $this->tarifa->id, $hoy->addDays(5), $hoy->addDays(7), 2)->count())->toBe(0)
            ->and($motor->cargosOpcionales($this->propiedad, $this->unidad, $this->tarifa->id, $hoy->addDays(5), $hoy->addDays(7), 3)->count())->toBe(1);
    });

    it('se capturan en la ficha de la unidad', function (): void {
        Livewire::actingAs($this->gerente)->test(EditUnidad::class, ['record' => $this->unidad->id])
            ->fillForm(['camas_extra' => 2])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->unidad->refresh()->camas_extra)->toBe(2);
    });
});

describe('calendario público', function (): void {
    beforeEach(function (): void {
        $this->mes = CarbonImmutable::parse($this->propiedad->hoy()->addMonth()->format('Y-m-01'));
        $this->url = '/reservar/posada/calendario?mes='.$this->mes->format('Y-m').'&idioma=es';
        $this->dia = fn (array $semanas, string $fecha): ?array => collect($semanas)->flatten(1)->filter()->first(fn (array $d): bool => $d['fecha']->format('Y-m-d') === $fecha);
    });

    it('dice qué días hay lugar, y un día con lugar lleva a su búsqueda', function (): void {
        $llegada = $this->mes->addDays(9);
        ($this->reservar)(['llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d')]);

        $semanas = (new CalendarioPublico($this->propiedad))->semanas($this->mes);

        expect(($this->dia)($semanas, $llegada->format('Y-m-d'))['libre'])->toBeFalse()
            ->and(($this->dia)($semanas, $llegada->addDay()->format('Y-m-d'))['libre'])->toBeFalse()
            // El día de salida ya se puede vender.
            ->and(($this->dia)($semanas, $llegada->addDays(2)->format('Y-m-d'))['libre'])->toBeTrue();

        $this->get($this->url)->assertOk()->assertSee('llegada='.$llegada->addDays(2)->format('Y-m-d'), false)->assertDontSee('llegada='.$llegada->format('Y-m-d').'&', false);
    });

    it('con varias unidades, hay lugar mientras quede una', function (): void {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2]);
        $llegada = $this->mes->addDays(9);
        ($this->reservar)(['llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d')]);

        expect(($this->dia)((new CalendarioPublico($this->propiedad))->semanas($this->mes), $llegada->format('Y-m-d'))['libre'])->toBeTrue();
    });

    it('nunca enseña quién está', function (): void {
        $llegada = $this->mes->addDays(9);
        ($this->reservar)(['llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(2)->format('Y-m-d'), 'comentario' => 'Secreto']);

        $this->get($this->url)->assertOk()->assertDontSee('Ejemplo')->assertDontSee('Secreto');
    });

    it('cuenta los bloqueos de otros calendarios, y las unidades sin tarifa no cuentan', function (): void {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'SIN', 'nombre' => 'Sin tarifa', 'capacidad' => 2]);
        $llegada = $this->mes->addDays(14);
        $fuente = App\Models\FuenteIcal::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'nombre' => 'Airbnb', 'url' => 'https://example.com/x.ics']);
        DB::table('bloqueos_externos')->insert(['fuente_id' => $fuente->id, 'propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'uid' => 'abc', 'llegada' => $llegada->format('Y-m-d'), 'salida' => $llegada->addDays(3)->format('Y-m-d'), 'primera_vez_en' => now('UTC'), 'visto_en' => now('UTC')]);

        $semanas = (new CalendarioPublico($this->propiedad))->semanas($this->mes);

        expect(($this->dia)($semanas, $llegada->addDay()->format('Y-m-d'))['libre'])->toBeFalse()
            ->and(($this->dia)($semanas, $llegada->addDays(3)->format('Y-m-d'))['libre'])->toBeTrue();
    });

    it('una unidad fuera de servicio no cuenta', function (): void {
        Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2]);
        $this->unidad->estado()->updateOrCreate([], ['fuera_de_servicio' => true]);
        Unidad::query()->where('codigo', '102')->sole()->estado()->updateOrCreate([], ['fuera_de_servicio' => true]);

        expect(($this->dia)((new CalendarioPublico($this->propiedad))->semanas($this->mes), $this->mes->addDays(3)->format('Y-m-d'))['libre'])->toBeFalse();
    });

    it('lo pasado no se puede pedir, y no deja ir más allá de lo que se reserva', function (): void {
        $hoy = $this->propiedad->hoy();
        $esteMes = CarbonImmutable::parse($hoy->format('Y-m-01'));
        $motor = MotorReservas::DIAS_HORIZONTE;

        $this->get('/reservar/posada/calendario?mes=1999-01&idioma=es')->assertOk()->assertSee($esteMes->format('Y').'')->assertDontSee('mes='.$esteMes->subMonth()->format('Y-m'), false);
        $ultimo = CarbonImmutable::parse($hoy->addDays($motor)->format('Y-m-01'));
        $this->get('/reservar/posada/calendario?mes='.$ultimo->format('Y-m').'&idioma=es')->assertOk()->assertDontSee('mes='.$ultimo->addMonth()->format('Y-m'), false);
        $this->get('/reservar/posada/calendario?mes=basura&idioma=es')->assertOk();
    });

    it('las semanas van de lunes a domingo y tienen siete días', function (): void {
        $semanas = (new CalendarioPublico($this->propiedad))->semanas(CarbonImmutable::parse('2027-03-01'));

        // Marzo de 2027 empieza en lunes.
        expect($semanas[0][0]['fecha']->format('Y-m-d'))->toBe('2027-03-01')
            ->and(collect($semanas)->every(fn (array $s): bool => count($s) === 7))->toBeTrue()
            ->and(collect($semanas)->flatten(1)->filter()->count())->toBe(31);

        $semanas = (new CalendarioPublico($this->propiedad))->semanas(CarbonImmutable::parse('2027-02-01'));
        expect($semanas[0][0]['fecha']->format('Y-m-d'))->toBe('2027-02-01');

        $semanas = (new CalendarioPublico($this->propiedad))->semanas(CarbonImmutable::parse('2027-08-01'));
        // Agosto de 2027 empieza en domingo.
        expect($semanas[0][5])->toBeNull()
            ->and($semanas[0][6]['fecha']->format('Y-m-d'))->toBe('2027-08-01');
    });

    it('apagado no abre, y la búsqueda no lo menciona', function (): void {
        (new AjustesMotor($this->propiedad))->guardar(['calendario_publico' => false]);

        $this->get($this->url)->assertNotFound();
        $this->get('/reservar/posada?idioma=es')->assertOk()->assertDontSee('/calendario', false);
    });

    it('prendido, la búsqueda lo enlaza', function (): void {
        $this->get('/reservar/posada?idioma=es')->assertOk()->assertSee('/reservar/posada/calendario', false);
    });
});

describe('demostración', function (): void {
    it('trae camas extra y el calendario público', function (): void {
        $this->artisan('alojaflow:demo')->assertSuccessful();
        $demo = Propiedad::query()->where('es_demo', true)->sole();

        expect(Unidad::query()->where('propiedad_id', $demo->id)->where('camas_extra', 1)->count())->toBe(2)
            ->and((new AjustesMotor($demo))->calendarioPublico())->toBeTrue()
            ->and(ConceptoCargo::query()->where('propiedad_id', $demo->id)->where('por_cama_extra', true)->count())->toBe(1);
    });
});
