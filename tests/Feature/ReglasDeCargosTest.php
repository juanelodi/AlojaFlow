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
use App\Filament\Resources\ConceptosCargo\Pages\CreateConceptoCargo;
use App\Filament\Resources\ConceptosCargo\Pages\EditConceptoCargo;
use App\Models\ConceptoCargo;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\MotorReservas;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\ReglasCargo;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'minutos_retencion' => 30]],
    ]);
    $this->propiedad->refresh();
    $this->doble = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
    $this->otra = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Con desayuno', 'precio_noche' => 120000, 'personas_incluidas' => 2]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->doble->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 4, 'orden' => 1]);
    $this->unidad->otrasTarifas()->attach($this->otra->id);
    $this->segunda = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->doble->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 4, 'orden' => 2]);
    $this->cargo = fn (array $datos = []): ConceptoCargo => ConceptoCargo::query()->create($datos + ['propiedad_id' => $this->propiedad->id, 'nombre' => 'Desayuno', 'base' => BaseCargo::PorNoche, 'importe' => 10000, 'obligatorio' => true]);
    // Del lunes 1 al lunes 8 de marzo de 2027: siete noches.
    $this->cargos = fn (string $llegada = '2027-03-01', string $salida = '2027-03-08', array $elegidos = [], ?int $tarifaId = null, ?Unidad $unidad = null, int $personas = 2): array => Cotizador::presupuesto(($unidad ?? $this->unidad)->refresh(), CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida), $personas, $elegidos, tarifaId: $tarifaId)->cargos;
    $this->importe = fn (array $cargos): ?int => $cargos[0]['importe'] ?? null;
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->actingAs($this->gerente);
    $this->hoy = $this->propiedad->hoy();
    $this->datosMotor = fn (array $mas = []): array => $mas + [
        'tarifa_id' => $this->doble->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'), 'personas' => 2,
        'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '+52 55 5550 0101', 'acepto' => '1',
    ];
});

afterEach(fn () => Ajustes::olvidar());

describe('las noches', function (): void {
    it('sin reglas, se cobra como siempre', function (): void {
        ($this->cargo)();

        expect(($this->importe)(($this->cargos)()))->toBe(70000);
    });

    it('cuenta solo las noches de la semana marcadas', function (): void {
        // Sin el domingo: seis de las siete noches.
        ($this->cargo)(['dias_semana' => [1, 2, 3, 4, 5, 6]]);

        expect(($this->importe)(($this->cargos)()))->toBe(60000);
    });

    it('la noche es la del día en que duermen', function (): void {
        ($this->cargo)(['dias_semana' => [6]]);

        // Del sábado 6 al domingo 7: sí.
        expect(($this->importe)(($this->cargos)('2027-03-06', '2027-03-07')))->toBe(10000)
            // Del domingo 7 al lunes 8: esa noche no es de sábado.
            ->and(($this->cargos)('2027-03-07', '2027-03-08'))->toBe([]);
    });

    it('cada cuántas noches divide lo que ya contó, y no cobra antes de tiempo', function (): void {
        ($this->cargo)(['cada_noches' => 3]);

        expect(($this->importe)(($this->cargos)()))->toBe(20000)
            ->and(($this->cargos)('2027-03-01', '2027-03-03'))->toBe([])
            ->and(($this->importe)(($this->cargos)('2027-03-01', '2027-03-04')))->toBe(10000);
    });

    it('también vale por persona y noche', function (): void {
        ($this->cargo)(['base' => BaseCargo::PorPersonaNoche, 'dias_semana' => [1]]);

        // Solo el lunes 1, para tres personas.
        expect(($this->importe)(($this->cargos)(personas: 3)))->toBe(30000);
    });

    it('en un cargo que no es por noche, los días no cuentan', function (): void {
        ($this->cargo)(['base' => BaseCargo::PorEstancia, 'dias_semana' => [7], 'cada_noches' => 5]);

        expect(($this->importe)(($this->cargos)('2027-03-01', '2027-03-02')))->toBe(10000);
    });
});

describe('las condiciones', function (): void {
    it('por noche, solo cuenta las noches dentro de sus fechas', function (): void {
        ($this->cargo)(['desde' => '2027-03-03', 'hasta' => '2027-03-04']);

        expect(($this->importe)(($this->cargos)()))->toBe(20000)
            ->and(($this->cargos)('2027-03-05', '2027-03-08'))->toBe([]);
    });

    it('«hasta» es la última noche que cuenta, y la salida no es una noche', function (): void {
        ($this->cargo)(['desde' => '2027-03-03', 'hasta' => '2027-03-03']);

        expect(($this->importe)(($this->cargos)('2027-03-03', '2027-03-04')))->toBe(10000)
            ->and(($this->cargos)('2027-03-01', '2027-03-03'))->toBe([])
            ->and(($this->cargos)('2027-03-04', '2027-03-05'))->toBe([]);
    });

    it('lo que no es por noche se cobra si alguna noche cae dentro', function (): void {
        ($this->cargo)(['base' => BaseCargo::PorEstancia, 'desde' => '2027-03-05', 'hasta' => '2027-03-20']);

        expect(($this->importe)(($this->cargos)()))->toBe(10000)
            ->and(($this->cargos)('2027-03-01', '2027-03-05'))->toBe([])
            ->and(($this->cargos)('2027-03-21', '2027-03-23'))->toBe([]);
    });

    it('pide un mínimo de noches', function (): void {
        ($this->cargo)(['noches_minimas' => 3]);

        expect(($this->cargos)('2027-03-01', '2027-03-03'))->toBe([])
            ->and(($this->importe)(($this->cargos)('2027-03-01', '2027-03-04')))->toBe(30000);
    });

    it('solo con ciertas tarifas', function (): void {
        ($this->cargo)(['solo_tarifas' => [$this->otra->id]]);

        expect(($this->cargos)())->toBe([])
            ->and(($this->importe)(($this->cargos)(tarifaId: $this->otra->id)))->toBe(70000);
    });

    it('solo en ciertas unidades', function (): void {
        ($this->cargo)(['solo_unidades' => [$this->segunda->id]]);

        expect(($this->cargos)())->toBe([])
            ->and(($this->importe)(($this->cargos)(unidad: $this->segunda)))->toBe(70000);
    });

    it('lo que alguien añadió a mano se cobra igual, por toda la estancia', function (): void {
        $cargo = ($this->cargo)(['obligatorio' => false, 'dias_semana' => [7], 'solo_tarifas' => [$this->otra->id], 'noches_minimas' => 30]);

        expect(($this->importe)(($this->cargos)(elegidos: [$cargo->id => 1.0])))->toBe(70000);
    });

    it('cuenta bien las noches aunque las fechas traigan una zona con horario de verano', function (): void {
        $cargo = ($this->cargo)(['noches_minimas' => 2, 'dias_semana' => [6, 7]]);
        // Cambio de hora en Madrid: del sábado 27 al lunes 29 de marzo de 2027 hay menos de 48 horas.
        $llegada = CarbonImmutable::parse('2027-03-27', 'Europe/Madrid');
        $salida = CarbonImmutable::parse('2027-03-29', 'Europe/Madrid');

        expect(ReglasCargo::nochesQueCuentan($cargo, $llegada, $salida))->toBe(2)
            ->and(ReglasCargo::aplica($cargo, $this->unidad, null, $llegada, $salida))->toBeTrue();
    });

    it('uno opcional sin elegir no se suma aunque cumpla', function (): void {
        ($this->cargo)(['obligatorio' => false]);

        expect(($this->cargos)())->toBe([]);
    });
});

describe('la reserva', function (): void {
    it('guarda los cargos que tocan y recalcula al cambiar las fechas', function (): void {
        ($this->cargo)(['dias_semana' => [6]]);
        $gestor = app(GestorReservas::class);
        $datos = ['unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'personas' => 2, 'total' => 700000, 'estado' => EstadoReserva::Confirmada->value, 'cargos' => []];

        $reserva = $gestor->crear($this->propiedad, $datos + ['llegada' => '2027-03-01', 'salida' => '2027-03-08']);
        expect($reserva->cargos()->sum('importe'))->toBe('10000');

        $cambiada = $gestor->modificar($reserva, $datos + ['llegada' => '2027-03-01', 'salida' => '2027-03-03']);
        expect($cambiada->cargos()->count())->toBe(0);
    });
});

describe('el motor', function (): void {
    it('ofrece un opcional solo si cumple sus reglas, y dice qué noches cuenta', function (): void {
        $lunes = $this->hoy->addDays(5);
        ($this->cargo)(['nombre' => 'Sí', 'obligatorio' => false, 'dias_semana' => [$lunes->dayOfWeekIso]]);
        ($this->cargo)(['nombre' => 'Otra tarifa', 'obligatorio' => false, 'solo_tarifas' => [$this->otra->id]]);
        ($this->cargo)(['nombre' => 'Muy largo', 'obligatorio' => false, 'noches_minimas' => 30]);
        $motor = app(MotorReservas::class);

        $ofrecidos = $motor->cargosOpcionales($this->propiedad, $this->unidad->refresh(), $this->doble->id, $lunes, $lunes->addDays(2));

        expect($ofrecidos->pluck('nombre')->all())->toBe(['Sí']);

        $pagina = $this->get('/reservar/posada/datos?llegada='.$lunes->format('Y-m-d').'&salida='.$lunes->addDays(2)->format('Y-m-d').'&personas=2&tarifa='.$this->doble->id)->assertOk();
        $pagina->assertDontSee('Muy largo')->assertSee('Sí');
    });

    it('no se puede colar uno que no cumple', function (): void {
        $cargo = ($this->cargo)(['nombre' => 'Muy largo', 'obligatorio' => false, 'noches_minimas' => 30]);

        $this->post('/reservar/posada', ($this->datosMotor)(['cargos' => [$cargo->id]]))->assertRedirect();

        $reserva = Reserva::query()->sole();
        expect($reserva->cargos()->count())->toBe(0)
            ->and($reserva->total)->toBe(200000);
    });

    it('el obligatorio con reglas entra en el precio de la oferta y de la reserva', function (): void {
        ($this->cargo)(['nombre' => 'Solo con la otra', 'solo_tarifas' => [$this->otra->id]]);

        $ofertas = collect(app(MotorReservas::class)->ofertas($this->propiedad, $this->hoy->addDays(5), $this->hoy->addDays(7), 2))->keyBy(fn ($o): string => $o->tarifa->nombre);
        expect($ofertas['Doble']->presupuesto->total())->toBe(200000)
            ->and($ofertas['Con desayuno']->presupuesto->total())->toBe(260000);

        $this->post('/reservar/posada', ($this->datosMotor)(['tarifa_id' => $this->otra->id]))->assertRedirect();
        expect(Reserva::query()->sole()->total)->toBe(260000)
            ->and(Reserva::query()->sole()->cargos()->sole()->nombre)->toBe('Solo con la otra');
    });
});

describe('el panel', function (): void {
    it('guarda las reglas, y las de por noche solo salen en un cargo por noche', function (): void {
        Livewire::actingAs($this->gerente)->test(CreateConceptoCargo::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Sábanas'])
            ->set('data.base', BaseCargo::PorEstancia->value)
            ->assertFormFieldHidden('dias_semana')
            ->assertFormFieldHidden('cada_noches')
            ->set('data.base', BaseCargo::PorNoche->value)
            ->assertFormFieldVisible('dias_semana')
            ->fillForm(['importe' => '100', 'dias_semana' => ['1', '2'], 'cada_noches' => 3, 'noches_minimas' => 2, 'desde' => '2027-01-01', 'hasta' => '2027-12-31', 'solo_tarifas' => [$this->otra->id], 'solo_unidades' => [$this->unidad->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        $cargo = ConceptoCargo::query()->where('nombre', 'Sábanas')->sole();
        expect($cargo->dias_semana)->toBe([1, 2])
            ->and($cargo->cada_noches)->toBe(3)
            ->and($cargo->noches_minimas)->toBe(2)
            ->and($cargo->desde->format('Y-m-d'))->toBe('2027-01-01')
            ->and($cargo->solo_tarifas)->toBe([$this->otra->id])
            ->and($cargo->solo_unidades)->toBe([$this->unidad->id]);
    });

    it('vacías quedan sin regla, y se vuelven a enseñar', function (): void {
        $cargo = ($this->cargo)(['dias_semana' => [1, 7], 'solo_tarifas' => [$this->otra->id]]);

        Livewire::actingAs($this->gerente)->test(EditConceptoCargo::class, ['record' => $cargo->id])
            ->assertFormSet(['dias_semana' => [1, 7], 'solo_tarifas' => [$this->otra->id]])
            ->fillForm(['dias_semana' => [], 'solo_tarifas' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($cargo->refresh()->dias_semana)->toBeNull()
            ->and($cargo->solo_tarifas)->toBeNull();
    });

    it('«hasta» no puede ir antes de «desde», y sin «desde» no estorba', function (): void {
        $cargo = ($this->cargo)();

        Livewire::actingAs($this->gerente)->test(EditConceptoCargo::class, ['record' => $cargo->id])
            ->fillForm(['desde' => '2027-03-10', 'hasta' => '2027-03-01'])
            ->call('save')
            ->assertHasFormErrors(['hasta']);

        Livewire::actingAs($this->gerente)->test(EditConceptoCargo::class, ['record' => $cargo->id])
            ->fillForm(['hasta' => '2027-03-01'])
            ->call('save')
            ->assertHasNoFormErrors();
    });

    it('no acepta tarifas ni unidades de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajena = Tarifa::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajena', 'precio_noche' => 100, 'personas_incluidas' => 2]);

        expect(fn () => App\Filament\Resources\ConceptosCargo\ConceptoCargoResource::comprobarReferencias(['solo_tarifas' => [$ajena->id]], $this->propiedad->id))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
        App\Filament\Resources\ConceptosCargo\ConceptoCargoResource::comprobarReferencias(['solo_tarifas' => [$this->otra->id], 'solo_unidades' => null], $this->propiedad->id);
    });

    it('el resumen dice los días y cada cuántas noches, en el idioma de quien mira', function (): void {
        $cargo = ($this->cargo)(['dias_semana' => [6, 1], 'cada_noches' => 2]);
        app()->setLocale('es');

        expect(ReglasCargo::resumen($cargo))->toContain('lun')->toContain('sáb')->toContain(__('ui.cargos.cada_noches_resumen', ['noches' => 2]))
            ->and(ReglasCargo::etiqueta($cargo))->toStartWith(BaseCargo::PorNoche->etiqueta())
            ->and(ReglasCargo::etiqueta(($this->cargo)(['nombre' => 'Sin reglas'])))->toBe(BaseCargo::PorNoche->etiqueta());
    });

    it('un recepcionista no los administra', function (): void {
        $recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
        $this->actingAs($recepcion);

        expect(App\Filament\Resources\ConceptosCargo\ConceptoCargoResource::canViewAny())->toBeFalse();
    });
});

describe('demostración', function (): void {
    it('trae un cargo cada 3 noches', function (): void {
        $this->artisan('alojaflow:demo')->assertSuccessful();
        $demo = Propiedad::query()->where('es_demo', true)->sole();

        expect(ConceptoCargo::query()->where('propiedad_id', $demo->id)->whereNotNull('cada_noches')->sole()->cada_noches)->toBe(3);
    });
});
