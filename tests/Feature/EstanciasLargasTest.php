<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\Mensajero;
use App\Comunicacion\PlantillasBase;
use App\Comunicacion\VariablesMensaje;
use App\Enums\AccionAutomatizacion;
use App\Enums\BaseCargo;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoEnvio;
use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\ModoTarifa;
use App\Enums\PaquetePais;
use App\Enums\TipoContacto;
use App\Filament\Pages\Mensualidades as PaginaMensualidades;
use App\Filament\Resources\Reservas\Pages\ViewReserva;
use App\Filament\Resources\Reservas\RelationManagers\CuotasRelationManager;
use App\Models\Automatizacion;
use App\Models\CuotaReserva;
use App\Models\Envio;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Impuesto;
use App\Models\Omision;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Pagos\CobrosEnLinea;
use App\Publico\MiReserva;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\Mensualidades;
use App\Seguridad\Acceso;
use App\Servicios\SeguimientoMensualidades;
use App\Support\Ajustes;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'paquete_pais' => PaquetePais::Mexico, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es', 'correo' => 'posada@example.com'])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Estudio', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'precio_persona_extra' => 10000, 'precio_semana' => 490000, 'precio_mes' => 1200000]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '101', 'nombre' => 'Cuarto 101', 'capacidad' => 4, 'activa' => true]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com', 'orden' => 0]);
    $this->huesped->contactos()->create(['tipo' => TipoContacto::Telefono, 'valor' => '+52 55 1234 5678', 'valor_normalizado' => '+525512345678', 'orden' => 1]);
    $this->hoy = $this->propiedad->hoy();
    $this->fecha = fn (string $fecha): CarbonImmutable => CarbonImmutable::parse($fecha);
    $this->precio = fn (string $llegada, string $salida, int $personas = 2) => Cotizador::cotizar($this->unidad->refresh(), CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida), $personas);
    $this->reservar = fn (int $total = 3600000, int $desde = 0, int $noches = 90, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($desde + $noches)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => $total,
    ]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->portero = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($this->gerente);
});

afterEach(fn () => Ajustes::olvidar());

describe('precio por semana y por mes', function (): void {
    it('un mes completo cuesta lo mismo con 28, 30 o 31 noches, y dos meses, el doble', function (): void {
        expect(($this->precio)('2027-02-01', '2027-03-01')->total)->toBe(1200000)
            ->and(($this->precio)('2027-04-01', '2027-05-01')->total)->toBe(1200000)
            ->and(($this->precio)('2027-01-01', '2027-02-01')->total)->toBe(1200000)
            ->and(($this->precio)('2027-01-15', '2027-03-15')->total)->toBe(2400000)
            ->and(($this->precio)('2027-01-31', '2027-02-28')->total)->toBe(1200000);
    });

    it('lo que sobra de un mes se cobra a treintavos, y la suma de las noches da el total', function (): void {
        $cotizacion = ($this->precio)('2027-01-15', '2027-02-20');

        expect($cotizacion->total)->toBe(1200000 + 5 * 40000)
            ->and($cotizacion->estanciaLarga)->toBe(Mensualidades::MES)
            ->and(array_sum($cotizacion->noches))->toBe($cotizacion->total)
            ->and($cotizacion->noches)->toHaveCount(36);
    });

    it('28 noches que no llenan un mes de calendario también van a precio de mes', function (): void {
        // Del 1 al 29 de enero: faltan dos noches para el mes, y se cobran a treintavos.
        expect(($this->precio)('2027-01-01', '2027-01-29')->total)->toBe(28 * 40000);
    });

    it('de 7 a 27 noches va a precio de semana; con menos, por noche', function (): void {
        $semana = ($this->precio)('2027-01-04', '2027-01-11');
        expect($semana->total)->toBe(490000)
            ->and($semana->estanciaLarga)->toBe(Mensualidades::SEMANA)
            ->and(($this->precio)('2027-01-04', '2027-01-14')->total)->toBe(700000);

        $corta = ($this->precio)('2027-01-04', '2027-01-10');
        expect($corta->total)->toBe(600000)
            ->and($corta->estanciaLarga)->toBeNull();
    });

    it('una tarifa con solo precio de semana también lo usa en un mes, y sin precios todo sigue igual', function (): void {
        $this->tarifa->update(['precio_mes' => null]);
        expect(($this->precio)('2027-01-01', '2027-02-01')->total)->toBe(31 * 70000);

        $this->tarifa->update(['precio_semana' => null]);
        $normal = ($this->precio)('2027-01-04', '2027-01-14');
        expect($normal->total)->toBe(1000000)
            ->and($normal->estanciaLarga)->toBeNull();
    });

    it('el centavo que no sale justo no se pierde', function (): void {
        $this->tarifa->update(['precio_mes' => 1000000]);
        $cotizacion = ($this->precio)('2027-01-01', '2027-02-01');

        expect($cotizacion->total)->toBe(1000000)
            ->and(array_sum($cotizacion->noches))->toBe(1000000)
            ->and(max($cotizacion->noches) - min($cotizacion->noches))->toBeLessThanOrEqual(1);
    });

    it('la persona extra se cobra por noche, y por persona se multiplica', function (): void {
        expect(($this->precio)('2027-02-01', '2027-03-01', 3)->total)->toBe(1200000 + 28 * 10000);

        $this->tarifa->update(['modo' => ModoTarifa::PorPersona]);
        expect(($this->precio)('2027-02-01', '2027-03-01', 3)->total)->toBe(3600000);
    });

    it('una temporada sin esos precios cobra su precio normal; con ellos, los suyos', function (): void {
        $temporada = TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Navidad', 'desde' => '2027-01-11', 'hasta' => '2027-01-20', 'precio_noche' => 200000]);

        // Del 1 al 11, y del 21 al 1 de febrero, a precio de mes; las 10 de Navidad, a su precio.
        $cotizacion = ($this->precio)('2027-01-01', '2027-02-01');
        expect($cotizacion->total)->toBe((int) round(1200000 / 31 * 21) + 10 * 200000);

        $temporada->update(['precio_mes' => 3100000]);
        expect(($this->precio)('2027-01-01', '2027-02-01')->total)->toBe((int) round(1200000 / 31 * 21 + 3100000 / 31 * 10));
    });

    it('el fin de semana no encarece un precio de semana', function (): void {
        $this->tarifa->update(['precio_fin_semana' => 500000]);

        expect(($this->precio)('2027-01-04', '2027-01-11')->total)->toBe(490000)
            ->and(($this->precio)('2027-01-04', '2027-01-10')->total)->toBe(100000 * 4 + 500000 * 2);
    });

    it('con el módulo apagado no se usan', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        expect(($this->precio)('2027-01-04', '2027-01-11')->total)->toBe(700000)
            ->and(($this->precio)('2027-01-04', '2027-01-11')->estanciaLarga)->toBeNull();
    });

    it('el formulario de la tarifa los guarda, también en la temporada, y vacío no es cero', function (): void {
        Livewire::test(App\Filament\Resources\Tarifas\Pages\EditTarifa::class, ['record' => $this->tarifa->id])
            ->fillForm(['precio_semana' => '5000', 'precio_mes' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->tarifa->refresh()->precio_semana)->toBe(500000)
            ->and($this->tarifa->precio_mes)->toBeNull();
    });
});

describe('impuesto de estancia larga', function (): void {
    beforeEach(function (): void {
        $this->impuesto = Impuesto::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '002', 'nombre' => 'Ocupación', 'base' => BaseCargo::PorcentajeAlojamiento, 'tasa' => 10, 'incluido_en_precio' => false, 'exento_desde_noches' => 30, 'orden' => 1]);
        $this->presupuesto = fn (string $llegada, string $salida) => Cotizador::presupuesto($this->unidad->refresh(), CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida), 2);
    });

    it('se cobra hasta antes del tope; al llegar, ya no', function (): void {
        expect(($this->presupuesto)('2027-01-01', '2027-01-30')->impuestos)->toHaveCount(1)
            ->and(($this->presupuesto)('2027-01-01', '2027-01-31')->impuestos)->toBe([]);
    });

    it('si se alarga la estancia hasta el tope, deja de constar como cobrado', function (): void {
        $reserva = ($this->reservar)(1000000, 0, 29);
        expect($reserva->impuestos()->count())->toBe(1);

        app(GestorReservas::class)->cambiarEstancia($reserva, $this->unidad->id, $this->hoy, $this->hoy->addDays(30));

        expect($reserva->refresh()->impuestos()->count())->toBe(0);
    });

    it('sin tope, nada cambia', function (): void {
        $this->impuesto->update(['exento_desde_noches' => null]);

        expect(($this->presupuesto)('2027-01-01', '2027-03-01')->impuestos)->toHaveCount(1);
    });

    it('el formulario lo guarda', function (): void {
        Livewire::test(App\Filament\Resources\Impuestos\Pages\EditImpuesto::class, ['record' => $this->impuesto->id])
            ->assertFormFieldExists('exento_desde_noches')
            ->fillForm(['exento_desde_noches' => 45])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->impuesto->refresh()->exento_desde_noches)->toBe(45);
    });
});

describe('calendario de cobros', function (): void {
    it('reparte por mes con la misma cantidad, y el último cubre lo que no llena un mes', function (): void {
        $reserva = app(GestorReservas::class)->crear($this->propiedad, [
            'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-01-15', 'salida' => '2027-03-20', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 2600000,
        ]);

        expect(app(Mensualidades::class)->generar($reserva, Mensualidades::MES, ($this->fecha)('2027-01-15')))->toBe(3);

        $cuotas = $reserva->cuotas()->get();
        // 2 meses y 5 noches: 2.1667 partes, a 12 000 cada una.
        expect($cuotas->pluck('importe')->all())->toBe([1200000, 1200000, 200000])
            ->and($cuotas->sum('importe'))->toBe(2600000)
            ->and($cuotas->map(fn (CuotaReserva $c): string => $c->vence_en->format('Y-m-d'))->all())->toBe(['2027-01-15', '2027-02-15', '2027-03-15'])
            ->and($cuotas->map(fn (CuotaReserva $c): string => $c->desde->format('Y-m-d').'/'.$c->hasta->format('Y-m-d'))->all())
            ->toBe(['2027-01-15/2027-02-15', '2027-02-15/2027-03-15', '2027-03-15/2027-03-20'])
            ->and(Mensualidades::de($reserva)['cuadra'])->toBeTrue();
    });

    it('reparte por semana, y la suma siempre da el total aunque no salga justo', function (): void {
        $reserva = ($this->reservar)(1000000, 0, 21);

        expect(app(Mensualidades::class)->generar($reserva, Mensualidades::SEMANA, $this->hoy))->toBe(3)
            ->and($reserva->cuotas()->get()->pluck('importe')->all())->toBe([333333, 333333, 333334]);
    });

    it('el primer vencimiento manda sobre los demás, aunque no coincida con la llegada', function (): void {
        $reserva = app(GestorReservas::class)->crear($this->propiedad, [
            'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => '2027-01-15', 'salida' => '2027-03-15', 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 2400000,
        ]);

        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, ($this->fecha)('2027-01-31'));

        expect($reserva->cuotas()->get()->map(fn (CuotaReserva $c): string => $c->vence_en->format('Y-m-d'))->all())->toBe(['2027-01-31', '2027-02-28']);
    });

    it('una estancia más corta que el periodo da un solo pago, por todo', function (): void {
        $reserva = ($this->reservar)(50000, 0, 5);

        expect(app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy))->toBe(1)
            ->and($reserva->cuotas()->sole()->importe)->toBe(50000);
    });

    it('lo pagado se aplica a las primeras en vencer, y lo de hoy no sale atrasado', function (): void {
        $reserva = ($this->reservar)(3000000, -10, 60);
        $reserva->cuotas()->createMany([
            ['importe' => 1000000, 'vence_en' => $this->hoy->subDays(5)->format('Y-m-d'), 'concepto' => 'Uno'],
            ['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Dos'],
            ['importe' => 1000000, 'vence_en' => $this->hoy->addDays(5)->format('Y-m-d'), 'concepto' => 'Tres'],
        ]);
        $estados = fn (): array => array_map(fn (array $f): string => $f['estado'], Mensualidades::de($reserva->refresh())['cuotas']);

        expect($estados())->toBe(['atrasada', 'pendiente', 'pendiente']);

        app(GestorReservas::class)->registrarPago($reserva, 1500000, MetodoPago::Efectivo, $this->hoy);

        expect($estados())->toBe(['pagada', 'parcial', 'pendiente'])
            ->and(Mensualidades::siguiente($reserva->refresh())['pagado'])->toBe(500000);
    });

    it('no reparte en una reserva cancelada', function (): void {
        $reserva = ($this->reservar)();
        app(GestorReservas::class)->cancelar($reserva);

        expect(fn () => app(Mensualidades::class)->generar($reserva->refresh(), Mensualidades::MES, $this->hoy))->toThrow(App\Reservas\ReservaRechazada::class);
    });

    it('generar otra vez sustituye lo que había, sin dejar pagos de más', function (): void {
        $reserva = ($this->reservar)(3600000, 0, 90);
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy);
        app(Mensualidades::class)->generar($reserva, Mensualidades::SEMANA, $this->hoy);

        expect($reserva->cuotas()->get()->sum('importe'))->toBe(3600000)
            ->and($reserva->cuotas()->count())->toBeGreaterThan(10);
    });
});

describe('lo que toca cobrar en línea', function (): void {
    it('sin calendario, sigue siendo el depósito', function (): void {
        $reserva = ($this->reservar)(1000000, 5, 10, ['deposito' => 200000]);

        expect(app(CobrosEnLinea::class)->porCobrar($reserva))->toBe(200000);
    });

    it('con calendario, el siguiente pago; si hay vencidos, todo lo vencido', function (): void {
        $reserva = ($this->reservar)(3000000, -10, 60);
        $reserva->cuotas()->createMany([
            ['importe' => 1000000, 'vence_en' => $this->hoy->addDays(5)->format('Y-m-d'), 'concepto' => 'Uno'],
            ['importe' => 2000000, 'vence_en' => $this->hoy->addDays(35)->format('Y-m-d'), 'concepto' => 'Dos'],
        ]);
        $cobros = app(CobrosEnLinea::class);

        expect($cobros->porCobrar($reserva))->toBe(1000000);

        CuotaReserva::query()->where('reserva_id', $reserva->id)->update(['vence_en' => $this->hoy->format('Y-m-d')]);
        expect($cobros->porCobrar($reserva->refresh()))->toBe(3000000);

        app(GestorReservas::class)->registrarPago($reserva, 3000000, MetodoPago::Efectivo, $this->hoy);
        expect($cobros->porCobrar($reserva->refresh()))->toBe(0);
    });

    it('si el depósito es mayor, manda el depósito', function (): void {
        $reserva = ($this->reservar)(3000000, 5, 60, ['deposito' => 1500000]);
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->addDays(5)->format('Y-m-d'), 'concepto' => 'Uno']);

        expect(app(CobrosEnLinea::class)->porCobrar($reserva))->toBe(1500000);
    });

    it('en una cancelada no hay nada que pagar del calendario', function (): void {
        $reserva = ($this->reservar)(3000000, 5, 60);
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Uno']);
        app(GestorReservas::class)->cancelar($reserva);

        expect(Mensualidades::porPagar($reserva->refresh()))->toBe(0);
    });

    it('con el módulo apagado se olvida del calendario', function (): void {
        $reserva = ($this->reservar)(3000000, 5, 60);
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Uno']);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        expect(app(CobrosEnLinea::class)->porCobrar($reserva))->toBe(0)
            ->and(Mensualidades::de($reserva)['cuotas'])->toBe([]);
    });
});

describe('recordatorios', function (): void {
    beforeEach(function (): void {
        PlantillasBase::prenderRecordatoriosDeCuota($this->propiedad);
        $this->mensajero = app(Mensajero::class);
        $this->antes = Automatizacion::query()->where('disparador', DisparadorAutomatizacion::AntesCuota->value)->sole();
        $this->vencida = Automatizacion::query()->where('disparador', DisparadorAutomatizacion::CuotaVencida->value)->sole();
        $this->antes->update(['hora' => null]);
        $this->vencida->update(['hora' => null]);
        // Reglas de ayer: lo que venció antes de crearlas no se reclama.
        Automatizacion::query()->whereKey([$this->antes->id, $this->vencida->id])->update(['creado_en' => now('UTC')->subDays(30)]);
        $this->cuota = function (int $vence, array $mas = []): CuotaReserva {
            $unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => 'R'.Unidad::query()->count(), 'nombre' => 'Otro cuarto', 'capacidad' => 4, 'activa' => true]);
            $reserva = ($this->reservar)(3000000, -5, 60, ['unidad_id' => $unidad->id]);
            $cuota = $reserva->cuotas()->create($mas + ['importe' => 1000000, 'vence_en' => $this->hoy->addDays($vence)->format('Y-m-d'), 'desde' => '2027-01-15', 'hasta' => '2027-02-15']);
            CuotaReserva::query()->whereKey($cuota->id)->update(['creado_en' => now('UTC')->subDays(30)]);

            return $cuota->refresh();
        };
    });

    it('avisa tres días antes, una sola vez, con lo que falta y en su idioma', function (): void {
        $cuota = ($this->cuota)(3);

        expect($this->mensajero->procesarTiempo())->toBe(1)
            ->and($this->mensajero->procesarTiempo())->toBe(0);

        $envio = Envio::query()->where('reserva_id', $cuota->reserva_id)->sole();
        expect($envio->destino)->toBe('ana@example.com')
            ->and($envio->cuerpo)->toContain('Ana')->toContain(Formato::dinero(1000000, 'MXN'))->toContain('2027')->not->toContain('{cuota_');
    });

    it('todavía no, si falta más que eso', function (): void {
        ($this->cuota)(4);

        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('si un día no corrieron las tareas, sale al siguiente', function (): void {
        ($this->cuota)(1);

        expect($this->mensajero->procesarTiempo())->toBe(1);
    });

    it('no le escribe a quien ya pagó, ni si la reserva se canceló', function (): void {
        $pagada = ($this->cuota)(2);
        app(GestorReservas::class)->registrarPago($pagada->reserva, 1000000, MetodoPago::Efectivo, $this->hoy);
        $cancelada = ($this->cuota)(2);
        app(GestorReservas::class)->cancelar($cancelada->reserva);

        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('avisa de lo vencido al día siguiente, y no de lo que venció hace tiempo', function (): void {
        $vencida = ($this->cuota)(-1);
        ($this->cuota)(-10);

        expect($this->mensajero->procesarTiempo())->toBe(1);
        expect(Envio::query()->where('reserva_id', $vencida->reserva_id)->sole()->asunto)->toContain('Posada');
    });

    it('no reclama un pago que ya estaba vencido cuando se capturó', function (): void {
        $cuota = ($this->cuota)(-1);
        CuotaReserva::query()->whereKey($cuota->id)->update(['creado_en' => now('UTC')]);

        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('no reclama lo que venció antes de crear la regla', function (): void {
        ($this->cuota)(-1);
        Automatizacion::query()->whereKey($this->vencida->id)->update(['creado_en' => now('UTC')]);

        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('respeta la hora, las condiciones y a quien pidió que no le escriban', function (): void {
        $cuota = ($this->cuota)(3);
        $this->antes->update(['condiciones' => ['estado' => EstadoReserva::SinConfirmar->value]]);
        expect($this->mensajero->procesarTiempo())->toBe(0);

        $this->travelTo(CarbonImmutable::parse($this->hoy->format('Y-m-d').' 12:00:00', 'America/Mexico_City'));
        $this->antes->update(['condiciones' => null, 'hora' => '18:00']);
        expect($this->mensajero->procesarTiempo())->toBe(0);

        $this->antes->update(['hora' => null]);
        Omision::query()->create(['huesped_id' => $cuota->reserva->titular_id, 'propiedad_id' => $this->propiedad->id, LugarOmision::Mensajes->value => true]);
        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('otra propiedad no recibe mensajes de esta regla', function (): void {
        ($this->cuota)(3);
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
        $unidad = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => '1', 'nombre' => 'Uno', 'capacidad' => 2, 'activa' => true]);
        $huesped = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Otra', 'apellido1' => 'Persona']);
        $reserva = app(GestorReservas::class)->crear($otra, ['unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->format('Y-m-d'), 'salida' => $this->hoy->addDays(10)->format('Y-m-d'), 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000]);
        $reserva->cuotas()->create(['importe' => 1000, 'vence_en' => $this->hoy->addDays(3)->format('Y-m-d'), 'concepto' => 'Otra']);
        CuotaReserva::query()->update(['creado_en' => now('UTC')->subDays(30)]);

        expect($this->mensajero->procesarTiempo())->toBe(1)
            ->and(Envio::query()->where('reserva_id', $reserva->id)->exists())->toBeFalse();
    });

    it('con el módulo apagado no manda nada', function (): void {
        ($this->cuota)(3);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        expect($this->mensajero->procesarTiempo())->toBe(0);
    });

    it('sin correo del huésped queda omitido, con el motivo a la vista', function (): void {
        $this->huesped->contactos()->delete();
        $cuota = ($this->cuota)(3);

        $this->mensajero->procesarTiempo();

        expect(Envio::query()->where('reserva_id', $cuota->reserva_id)->sole()->estado)->toBe(EstadoEnvio::Omitido);
    });

    it('prenderlos de nuevo respeta lo que se cambió, y vuelve a activar', function (): void {
        $this->antes->update(['dias' => 7, 'activa' => false]);
        Plantilla::query()->where('codigo', 'cuota_por_vencer')->update(['nombre' => 'Mi texto']);

        PlantillasBase::prenderRecordatoriosDeCuota($this->propiedad);

        expect($this->antes->refresh()->dias)->toBe(7)
            ->and($this->antes->activa)->toBeTrue()
            ->and(Plantilla::query()->where('codigo', 'cuota_por_vencer')->sole()->nombre)->toBe('Mi texto')
            ->and(Automatizacion::query()->count())->toBe(2)
            ->and($this->antes->accion)->toBe(AccionAutomatizacion::CorreoHuesped);
    });

    it('las variables del mensaje dicen lo que falta del primer pago sin cubrir', function (): void {
        $cuota = ($this->cuota)(3);
        app(GestorReservas::class)->registrarPago($cuota->reserva, 400000, MetodoPago::Efectivo, $this->hoy);

        $variables = VariablesMensaje::de($cuota->reserva->refresh(), 'es');
        expect($variables['cuota_importe'])->toBe(Formato::dinero(600000, 'MXN'))
            ->and($variables['cuota_periodo'])->toContain('2027');
    });

    it('sin calendario las variables quedan vacías', function (): void {
        $variables = VariablesMensaje::de(($this->reservar)(), 'es');

        expect($variables['cuota_importe'])->toBe('')
            ->and($variables['cuota_vence'])->toBe('')
            ->and($variables['cuota_periodo'])->toBe('');
    });
});

describe('en el panel', function (): void {
    it('un recepcionista genera, agrega, edita y quita; el estado se ve', function (): void {
        $reserva = ($this->reservar)(2400000, -40, 60);

        Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->callTableAction('generarCalendario', null, ['periodo' => Mensualidades::MES, 'primer_vencimiento' => $reserva->llegada->format('Y-m-d')])
            ->assertHasNoTableActionErrors()
            ->assertSee(__('estancias.cuotas.estados.atrasada'));

        expect($reserva->cuotas()->get()->sum('importe'))->toBe(2400000);

        $primera = $reserva->cuotas()->first();
        Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->callTableAction('editarCuota', $primera, ['concepto' => 'Primer mes', 'importe' => '1000', 'vence_en' => $this->hoy->addDay()->format('Y-m-d')])
            ->assertHasNoTableActionErrors()
            ->assertSee(__('estancias.cuotas.no_cuadra', ['suma' => Formato::dinero($reserva->cuotas()->get()->sum('importe'), 'MXN'), 'total' => Formato::dinero(2400000, 'MXN')]));

        expect($primera->refresh()->importe)->toBe(100000)
            ->and($primera->periodo())->toBe('Primer mes');

        Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->callTableAction('agregarCuota', null, ['concepto' => 'Depósito', 'importe' => '500', 'vence_en' => $this->hoy->format('Y-m-d')])
            ->assertHasNoTableActionErrors()
            ->callTableAction('quitarCuota', $primera);

        expect($reserva->cuotas()->where('concepto', 'Depósito')->sole()->importe)->toBe(50000)
            ->and(CuotaReserva::query()->find($primera->id))->toBeNull();
    });

    it('un pago agregado a mano necesita concepto', function (): void {
        $reserva = ($this->reservar)();

        Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->callTableAction('agregarCuota', null, ['concepto' => '', 'importe' => '500', 'vence_en' => $this->hoy->format('Y-m-d')])
            ->assertHasTableActionErrors(['concepto' => 'required']);
    });

    it('el botón de WhatsApp lleva el recordatorio, con el teléfono del huésped', function (): void {
        $reserva = ($this->reservar)();
        $cuota = $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);

        $url = Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->instance()->getTable()->getAction('recordarCuota')->record($cuota)->getUrl();

        expect($url)->toContain('wa.me/525512345678')->toContain(rawurlencode('Enero'));
    });

    it('quien no puede cambiar una reserva ya iniciada, tampoco cambia su calendario', function (): void {
        $reserva = ($this->reservar)(2400000, -5, 60);
        $cuota = $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);
        Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'sin_iniciadas' => true]]);

        Livewire::actingAs($this->recepcion->refresh())->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->assertSee('Enero')
            ->assertTableActionHidden('generarCalendario')
            ->assertTableActionHidden('agregarCuota')
            ->assertTableActionHidden('editarCuota', $cuota)
            ->assertTableActionHidden('quitarCuota', $cuota)
            ->assertTableActionVisible('recordarCuota', $cuota);
    });

    it('un contador lo ve, pero no cambia el calendario', function (): void {
        $contador = User::query()->create(['name' => 'Con', 'email' => 'con@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
        Acceso::asignarRoles($contador, [['propiedad_id' => $this->propiedad->id, 'rol' => 'contador']]);
        $reserva = ($this->reservar)();
        $cuota = $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);

        $this->actingAs($contador);
        expect(CuotasRelationManager::canViewForRecord($reserva, ViewReserva::class))->toBeTrue();

        Livewire::actingAs($contador)->test(CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => ViewReserva::class])
            ->assertTableActionHidden('generarCalendario')
            ->assertTableActionHidden('editarCuota', $cuota);
    });

    it('no se ve sin permiso, ni en un grupo, ni con el módulo apagado', function (): void {
        $reserva = ($this->reservar)();
        expect(CuotasRelationManager::canViewForRecord($reserva, ViewReserva::class))->toBeTrue();

        $this->actingAs($this->portero);
        expect(CuotasRelationManager::canViewForRecord($reserva, ViewReserva::class))->toBeFalse();

        $this->actingAs($this->gerente);
        $grupo = Grupo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Boda', 'codigo' => 'BODA-1']);
        $conGrupo = ($this->reservar)(1000, 100, 2, ['grupo_id' => $grupo->id]);
        expect(CuotasRelationManager::canViewForRecord($conGrupo, ViewReserva::class))->toBeFalse();

        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));
        expect(CuotasRelationManager::canViewForRecord($reserva, ViewReserva::class))->toBeFalse();
    });

    it('el formulario de la reserva avisa que aplicó el precio de estancia larga', function (): void {
        Livewire::test(App\Filament\Resources\Reservas\Pages\CreateReserva::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(15)->format('Y-m-d'), 'personas' => 2])
            ->assertSee(__('estancias.motor.aplicado.semana'));
    });

    it('una regla de pagos no se puede crear con el módulo apagado', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        Livewire::test(App\Filament\Resources\Automatizaciones\Pages\CreateAutomatizacion::class)
            ->fillForm(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Regla', 'disparador' => DisparadorAutomatizacion::AntesCuota->value, 'dias' => 3, 'accion' => AccionAutomatizacion::CorreoHuesped->value])
            ->call('create')
            ->assertHasFormErrors(['disparador']);
    });

    it('los disparadores tienen su nombre, y el módulo avisa si hay calendarios al apagarlo', function (): void {
        ($this->reservar)()->cuotas()->create(['importe' => 1000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Uno']);

        expect(DisparadorAutomatizacion::AntesCuota->etiqueta())->toBe('Días antes de un pago del calendario')
            ->and(DisparadorAutomatizacion::CuotaVencida->etiqueta())->toBe('Días después de un pago vencido')
            ->and(Modulos::enUso(Modulo::EstanciasLargas))->toBe(1);
    });

    it('la reserva abre con el calendario, también para un usuario sin administrador', function (): void {
        $reserva = ($this->reservar)();
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy);

        $this->actingAs($this->recepcion)->get(App\Filament\Resources\Reservas\ReservaResource::getUrl('view', ['record' => $reserva]))
            ->assertOk()
            ->assertSee(__('estancias.cuotas.titulo'));
    });
});

describe('seguimiento', function (): void {
    it('junta lo vencido y lo que viene, sin lo pagado, lo lejano ni lo de otra propiedad', function (): void {
        $reserva = ($this->reservar)(3000000, -40, 120);
        $reserva->cuotas()->createMany([
            ['importe' => 500000, 'vence_en' => $this->hoy->subDays(30)->format('Y-m-d'), 'concepto' => 'Pagada'],
            ['importe' => 500000, 'vence_en' => $this->hoy->subDay()->format('Y-m-d'), 'concepto' => 'Vencida'],
            ['importe' => 500000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Hoy'],
            ['importe' => 500000, 'vence_en' => $this->hoy->addDays(40)->format('Y-m-d'), 'concepto' => 'Lejana'],
        ]);
        app(GestorReservas::class)->registrarPago($reserva, 700000, MetodoPago::Efectivo, $this->hoy);
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);

        $resumen = SeguimientoMensualidades::resumen([$this->propiedad->id]);

        expect(array_map(fn (array $f): string => $f['cuota']->concepto, $resumen['vencidas']))->toBe(['Vencida'])
            ->and($resumen['vencidas'][0]['falta'])->toBe(300000)
            ->and(array_map(fn (array $f): string => $f['cuota']->concepto, $resumen['proximas']))->toBe(['Hoy'])
            ->and($resumen['vencidas'][0]['whatsapp'])->toContain('wa.me/')
            ->and(SeguimientoMensualidades::resumen([$otra->id])['vencidas'])->toBe([]);
    });

    it('la pantalla abre, con el permiso, y deja prender los recordatorios', function (): void {
        $reserva = ($this->reservar)();
        $cuota = $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->subDay()->format('Y-m-d'), 'concepto' => 'Enero']);

        Livewire::actingAs($this->gerente)->test(PaginaMensualidades::class)
            ->assertSee('Enero')
            ->assertSee(__('estancias.pagina.recordatorios_prender'))
            ->call('prenderRecordatorios')
            ->assertDontSee(__('estancias.pagina.recordatorios_prender'))
            ->call('anotarRecordatorio', $cuota->id);

        expect(Automatizacion::query()->count())->toBe(2)
            ->and(Envio::query()->where('reserva_id', $reserva->id)->sole()->cuerpo)->toContain('Enero');
    });

    it('con los mensajes apagados no ofrece prender recordatorios', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Mensajes)));

        Livewire::actingAs($this->gerente)->test(PaginaMensualidades::class)
            ->assertDontSee(__('estancias.pagina.recordatorios_prender'))
            ->call('prenderRecordatorios');

        expect(Automatizacion::query()->count())->toBe(0);
    });

    it('sin permiso no se entra', function (): void {
        Livewire::actingAs($this->portero)->test(PaginaMensualidades::class)->assertForbidden();
    });

    it('con el módulo apagado no se entra', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        Livewire::actingAs($this->gerente)->test(PaginaMensualidades::class)->assertForbidden();
    });

    it('no se puede anotar un recordatorio de la reserva de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es'])->refresh();
        $unidad = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => '1', 'nombre' => 'Uno', 'capacidad' => 2, 'activa' => true]);
        $huesped = Huesped::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Otra', 'apellido1' => 'Persona']);
        $ajena = app(GestorReservas::class)->crear($otra, ['unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->format('Y-m-d'), 'salida' => $this->hoy->addDays(10)->format('Y-m-d'), 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000]);
        $cuota = $ajena->cuotas()->create(['importe' => 1000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Ajena']);

        Livewire::actingAs($this->gerente)->test(PaginaMensualidades::class)
            ->call('anotarRecordatorio', $cuota->id)
            ->assertNotFound();
    });
});

describe('lo que ve el huésped', function (): void {
    beforeEach(function (): void {
        $this->portal = app(MiReserva::class);
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Transfiere a la cuenta 123']]]);
    });

    it('Mi reserva enseña sus pagos, sus estados y lo que toca pagar', function (): void {
        $reserva = ($this->reservar)(3000000, -5, 60);
        $reserva->cuotas()->createMany([
            ['importe' => 1000000, 'vence_en' => $this->hoy->subDays(5)->format('Y-m-d'), 'desde' => '2027-01-15', 'hasta' => '2027-02-15'],
            ['importe' => 2000000, 'vence_en' => $this->hoy->addDays(25)->format('Y-m-d'), 'concepto' => 'El resto'],
        ]);
        app(GestorReservas::class)->registrarPago($reserva, 1000000, MetodoPago::Efectivo, $this->hoy);

        $this->get($this->portal->urlDe($reserva->refresh()))
            ->assertOk()
            ->assertSee(__('estancias.publico.titulo'))
            ->assertSee('El resto')
            ->assertSee(__('estancias.cuotas.estados.pagada'))
            ->assertSee(__('estancias.cuotas.estados.pendiente'));
    });

    it('sin calendario no sale nada de eso', function (): void {
        $this->get($this->portal->urlDe(($this->reservar)()))
            ->assertOk()
            ->assertDontSee(__('estancias.publico.titulo'));
    });

    it('cancelada, ya no enseña pagos', function (): void {
        $reserva = ($this->reservar)();
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);
        app(GestorReservas::class)->cancelar($reserva);

        $this->get($this->portal->urlDe($reserva->refresh()))->assertOk()->assertDontSee('Enero');
    });

    it('el huésped manda su comprobante desde Mi reserva, y queda por revisar', function (): void {
        $reserva = ($this->reservar)();

        $url = $this->portal->urlDe($reserva);

        $this->get($url)->assertOk()->assertSee('Transfiere a la cuenta 123')->assertSee(__('grupos.comprobante.mandar'))->assertSee('name="_token"', false);

        $this->post($url.'/comprobante', ['importe' => '1000', 'fecha' => $this->hoy->format('Y-m-d'), 'referencia' => 'ABC', 'comprobante' => UploadedFile::fake()->image('pago.jpg', 400, 300)])
            ->assertRedirectContains('aviso=comprobante_recibido');

        expect($reserva->comprobantes()->sole()->importe)->toBe(100000);

        $this->get($url.'?aviso=comprobante_recibido')->assertSee(__('grupos.comprobante.aviso.comprobante_recibido'));
    });

    it('un comprobante con datos mal le dice qué pasó', function (): void {
        $reserva = ($this->reservar)();
        $url = $this->portal->urlDe($reserva);

        $this->post($url.'/comprobante', ['importe' => '', 'fecha' => $this->hoy->format('Y-m-d')])->assertRedirectContains('aviso=comprobante_datos');

        expect($reserva->comprobantes()->count())->toBe(0);
    });

    it('el importe que propone es lo que toca pagar, no todo el saldo', function (): void {
        $reserva = ($this->reservar)(3000000);
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);

        $this->get($this->portal->urlDe($reserva))->assertSee('value="10000.00"', false);
    });

    it('sin transferencia, no pide comprobante', function (): void {
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123', 'acepta_comprobantes' => false]]]);
        $reserva = ($this->reservar)();
        $url = $this->portal->urlDe($reserva);

        $this->get($url)->assertOk()->assertDontSee(__('grupos.comprobante.mandar'));
        $this->post($url.'/comprobante', ['importe' => '1000', 'fecha' => $this->hoy->format('Y-m-d')])->assertNotFound();
    });

    it('en la página de la reserva del motor también se ven, y el comprobante sigue llegando sin sesión', function (): void {
        $reserva = ($this->reservar)(3000000);
        $reserva->cuotas()->create(['importe' => 1000000, 'vence_en' => $this->hoy->format('Y-m-d'), 'concepto' => 'Enero']);
        $url = route('motor.reserva', ['codigo' => 'posada', 'publico' => $reserva->codigo_publico]);

        $this->get($url)->assertOk()->assertSee('Enero')->assertDontSee('name="_token"', false);

        $this->withHeader('Origin', (string) config('app.url'))->post(route('motor.comprobante', ['codigo' => 'posada', 'publico' => $reserva->codigo_publico]), ['importe' => '100', 'fecha' => $this->hoy->format('Y-m-d'), 'comprobante' => UploadedFile::fake()->image('pago.jpg', 400, 300)])
            ->assertRedirectContains('aviso=comprobante_recibido');
    });

    it('el motor enseña el precio por semana y por mes, y dice cuál aplicó', function (): void {
        $this->get('/reservar/posada?llegada='.$this->hoy->addDays(10)->format('Y-m-d').'&salida='.$this->hoy->addDays(20)->format('Y-m-d').'&personas=2')
            ->assertOk()
            ->assertSee(__('estancias.motor.semana', ['precio' => Formato::dinero(490000, 'MXN')]))
            ->assertSee(__('estancias.motor.mes', ['precio' => Formato::dinero(1200000, 'MXN')]))
            ->assertSee(__('estancias.motor.aplicado.semana'));
    });

    it('con el módulo apagado, el motor no los enseña', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::EstanciasLargas)));

        $this->get('/reservar/posada?llegada='.$this->hoy->addDays(10)->format('Y-m-d').'&salida='.$this->hoy->addDays(20)->format('Y-m-d').'&personas=2')
            ->assertOk()
            ->assertDontSee(__('estancias.motor.mes', ['precio' => Formato::dinero(1200000, 'MXN')]))
            ->assertDontSee(__('estancias.motor.aplicado.semana'));
    });
});
