<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\PlantillasBase;
use App\Documentos\DocumentosDePartida;
use App\Enums\BaseCargoCanal;
use App\Enums\CalculoCargoCanal;
use App\Enums\ClaseCargoCanal;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\CalendarioTarifas;
use App\Filament\Resources\Canales\Pages\EditCanal;
use App\Filament\Resources\Grupos\Pages\ListGrupos;
use App\Models\Automatizacion;
use App\Models\Canal;
use App\Models\CanalCargo;
use App\Models\DocumentoPropio;
use App\Models\Grupo;
use App\Models\Huesped;
use App\Models\Plantilla;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Paises\Espana;
use App\Paises\Mexico;
use App\Reservas\CargosSugeridos;
use App\Reservas\GestorGrupos;
use App\Reservas\GestorPreciosTarifa;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Reservas\UtilidadReserva;
use App\Servicios\CalendarioTarifas as ServicioCalendarioTarifas;
use App\Servicios\Informes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Casa', 'codigo' => 'casa', 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'moneda' => 'MXN', 'idioma' => 'es', 'zona_horaria' => 'America/Mexico_City']);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Normal', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'precio_persona_extra' => 0, 'estancia_minima' => 1]);
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2, 'tarifa_id' => $this->tarifa->id]);
    $this->otra = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '102', 'nombre' => '102', 'capacidad' => 2, 'tarifa_id' => $this->tarifa->id]);
    $this->ana = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Rivas']);
    $this->admin = User::factory()->create(['es_administrador' => true, 'activo' => true]);
    $this->reservar = fn (Unidad $unidad, string $llegada, string $salida, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $unidad->id, 'titular_id' => $this->ana->id, 'llegada' => $llegada, 'salida' => $salida, 'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000,
    ]);
});

describe('un grupo a partir de reservas ya creadas', function (): void {
    it('las junta sin cambiar fechas, unidad ni precio', function (): void {
        $a = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03');
        $b = ($this->reservar)($this->otra, '2030-03-01', '2030-03-03', ['total' => 150000, 'estado' => EstadoReserva::SinConfirmar->value]);

        $grupo = app(GestorGrupos::class)->crearDesdeReservas($this->propiedad, 'Boda', $this->ana->id, [$a->id, $b->id]);

        expect($a->refresh()->grupo_id)->toBe($grupo->id)
            ->and($b->refresh()->total)->toBe(150000)
            ->and($b->llegada->format('Y-m-d'))->toBe('2030-03-01')
            ->and($grupo->coordinador_id)->toBe($this->ana->id)
            ->and($grupo->estado->value)->toBe('tentativo');
    });

    it('rechaza una que ya está en un grupo, y no deja nada a medias', function (): void {
        $a = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03');
        $b = ($this->reservar)($this->otra, '2030-03-01', '2030-03-03');
        app(GestorGrupos::class)->crearDesdeReservas($this->propiedad, 'Uno', $this->ana->id, [$a->id]);

        expect(fn () => app(GestorGrupos::class)->crearDesdeReservas($this->propiedad, 'Dos', $this->ana->id, [$a->id, $b->id]))->toThrow(ReservaRechazada::class);
        expect(Grupo::query()->count())->toBe(1)->and($b->refresh()->grupo_id)->toBeNull();
    });

    it('rechaza la de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'pais' => 'MX', 'moneda' => 'MXN', 'idioma' => 'es', 'zona_horaria' => 'America/Mexico_City']);
        $a = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03');

        expect(fn () => app(GestorGrupos::class)->crearDesdeReservas($otra, 'X', $this->ana->id, [$a->id]))->toThrow(ReservaRechazada::class);
    });

    it('se hace desde la pantalla de grupos', function (): void {
        $a = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03');

        Livewire::actingAs($this->admin)->test(ListGrupos::class)
            ->callAction('desdeReservas', ['propiedad_id' => $this->propiedad->id, 'huesped_id' => $this->ana->id, 'reservas' => [$a->id], 'nombre' => 'Familia Rivas'])
            ->assertHasNoActionErrors();

        expect(Grupo::query()->where('nombre', 'Familia Rivas')->firstOrFail()->id)->toBe($a->refresh()->grupo_id);
    });
});

describe('plantillas de partida', function (): void {
    it('crea lo que falta y respeta lo que se cambió', function (): void {
        expect(PlantillasBase::sembrar($this->propiedad))->toBeGreaterThanOrEqual(11);
        Plantilla::query()->where('codigo', 'confirmacion')->update(['nombre' => 'La mía']);

        expect(PlantillasBase::sembrar($this->propiedad))->toBe(0)
            ->and(Plantilla::query()->where('codigo', 'confirmacion')->value('nombre'))->toBe('La mía')
            ->and(Automatizacion::query()->count())->toBe(0);
    });

    it('cada plantilla viene en los seis idiomas, con variables que existen', function (): void {
        PlantillasBase::sembrar($this->propiedad);

        foreach (Plantilla::query()->get() as $plantilla) {
            expect(array_keys($plantilla->cuerpo))->toHaveCount(6);
            foreach ($plantilla->cuerpo as $cuerpo) {
                preg_match_all('/\{(\w+)\}/', (string) $cuerpo, $variables);
                expect(array_diff($variables[1], App\Comunicacion\VariablesMensaje::DISPONIBLES))->toBe([]);
            }
        }
    });

    it('los envíos recomendados se prenden solo a pedido, una sola vez', function (): void {
        expect(PlantillasBase::prenderRecomendados($this->propiedad))->toBe(3)
            ->and(PlantillasBase::prenderRecomendados($this->propiedad))->toBe(0)
            ->and(Automatizacion::query()->count())->toBe(3);
    });

    it('los documentos de partida traen reglamento y hoja de limpieza', function (): void {
        expect(DocumentosDePartida::sembrar($this->propiedad))->toBe(5)
            ->and(DocumentoPropio::query()->pluck('nombre')->all())->toContain(__('documentos_propios.base.reglamento.nombre'), __('documentos_propios.base.limpieza.nombre'));
    });
});

describe('calendario de tarifas', function (): void {
    it('da el precio de cada noche con la misma regla que cobra', function (): void {
        $this->tarifa->update(['precio_fin_semana' => 150000]);
        TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Alta', 'desde' => '2030-03-11', 'hasta' => '2030-03-12', 'precio_noche' => 200000]);

        $datos = ServicioCalendarioTarifas::mes([$this->propiedad->id], CarbonImmutable::parse('2030-03-01'));
        $celdas = $datos['grupos'][0]['filas'][0]['celdas'];

        // El 1 de marzo de 2030 es viernes.
        expect($datos['dias'])->toHaveCount(31)
            ->and($celdas[0]['precio'])->toBe(150000)
            ->and($celdas[3]['precio'])->toBe(100000)
            ->and($celdas[10]['precio'])->toBe(200000)
            ->and($celdas[10]['temporada'])->toBe('Alta');
    });

    it('una derivada sale de la suya, con su ajuste', function (): void {
        $derivada = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'tarifa_base_id' => $this->tarifa->id, 'ajuste_porcentaje' => -10, 'precio_noche' => 0, 'estancia_minima' => 1]);

        $filas = ServicioCalendarioTarifas::mes([$this->propiedad->id], CarbonImmutable::parse('2030-03-01'))['grupos'][0]['filas'];
        $fila = collect($filas)->firstWhere('tarifa.id', $derivada->id);

        expect($fila['celdas'][3]['precio'])->toBe(90000);
    });

    it('poner precio recorta lo encimado: el día dice lo último que se escribió', function (): void {
        $larga = TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Alta', 'desde' => '2030-03-05', 'hasta' => '2030-03-20', 'precio_noche' => 200000]);
        $contenida = TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Puente', 'desde' => '2030-03-10', 'hasta' => '2030-03-11', 'precio_noche' => 300000]);

        app(GestorPreciosTarifa::class)->fijar($this->tarifa, CarbonImmutable::parse('2030-03-09'), CarbonImmutable::parse('2030-03-12'), 'Evento', 500000);

        $celdas = ServicioCalendarioTarifas::mes([$this->propiedad->id], CarbonImmutable::parse('2030-03-01'))['grupos'][0]['filas'][0]['celdas'];
        expect($celdas[7]['precio'])->toBe(200000)
            ->and($celdas[8]['precio'])->toBe(500000)
            ->and($celdas[11]['precio'])->toBe(500000)
            ->and($celdas[12]['precio'])->toBe(200000)
            ->and(TemporadaTarifa::query()->find($contenida->id))->toBeNull()
            ->and($larga->refresh()->hasta->format('Y-m-d'))->toBe('2030-03-08');
    });

    it('a una derivada no se le pone precio', function (): void {
        $derivada = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'tarifa_base_id' => $this->tarifa->id, 'precio_noche' => 0, 'estancia_minima' => 1]);

        expect(fn () => app(GestorPreciosTarifa::class)->fijar($derivada, CarbonImmutable::parse('2030-03-09'), CarbonImmutable::parse('2030-03-12'), 'X', 1000))->toThrow(ReservaRechazada::class);
    });

    it('se ve y se cambia desde su pantalla, sin mover lo vendido', function (): void {
        $reserva = ($this->reservar)($this->unidad, '2030-03-09', '2030-03-11');

        Livewire::actingAs($this->admin)->test(CalendarioTarifas::class, ['mes' => '2030-03'])
            ->assertOk()->assertSee('Normal')->assertSee('af-tar-celda', false)
            ->callAction('fijarPrecio', ['tarifa_id' => $this->tarifa->id, 'desde' => '2030-03-09', 'hasta' => '2030-03-10', 'nombre' => 'Evento', 'precio_noche' => '750.50'])
            ->assertHasNoActionErrors();

        expect(TemporadaTarifa::query()->where('nombre', 'Evento')->firstOrFail()->precio_noche)->toBe(75050)
            ->and($reserva->refresh()->total)->toBe(200000);
    });

    it('quien no maneja el catálogo no entra', function (): void {
        $this->actingAs(User::factory()->create(['activo' => true]))->get(CalendarioTarifas::getUrl())->assertForbidden();
    });
});

describe('utilidad de una reserva', function (): void {
    beforeEach(function (): void {
        $this->canal = Canal::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Booking', 'comision_porcentaje' => 15]);
        $this->cargo = fn (string $nombre, ClaseCargoCanal $clase, float $valor, BaseCargoCanal $base = BaseCargoCanal::TotalFinal, CalculoCargoCanal $calculo = CalculoCargoCanal::Porcentaje): CanalCargo => CanalCargo::query()->create(['canal_id' => $this->canal->id, 'nombre' => $nombre, 'clase' => $clase, 'calculo' => $calculo, 'valor' => $valor, 'base' => $base]);
    });

    it('resta comisión y cargos en la bruta, y las retenciones en la neta', function (): void {
        ($this->cargo)('Cobro', ClaseCargoCanal::ProcesamientoPago, 2);
        ($this->cargo)('IVA de comisión', ClaseCargoCanal::ImpuestoSobreCargos, 16, BaseCargoCanal::Comisiones);
        ($this->cargo)('ISR', ClaseCargoCanal::RetencionRenta, 4, BaseCargoCanal::SinImpuestos);
        $reserva = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03', ['origen_texto' => 'Booking', 'comision' => 30000]);

        $u = app(UtilidadReserva::class)->de($reserva);

        // Cobro 2 % de 2,000 = 40; IVA 16 % de (300 + 40) = 54.40; ISR 4 % de 2,000 = 80.
        expect($u['total_cargos'])->toBe(4000 + 5440)
            ->and($u['bruta'])->toBe(200000 - 30000 - 9440)
            ->and($u['total_retenciones'])->toBe(8000)
            ->and($u['neta'])->toBe(200000 - 30000 - 9440 - 8000);
    });

    it('distingue un cargo fijo por reserva y por noche, y se salta lo apagado', function (): void {
        ($this->cargo)('Fijo', ClaseCargoCanal::Servicio, 50, BaseCargoCanal::TotalFinal, CalculoCargoCanal::FijoReserva);
        ($this->cargo)('Noche', ClaseCargoCanal::Otro, 10, BaseCargoCanal::TotalFinal, CalculoCargoCanal::FijoNoche);
        ($this->cargo)('Apagado', ClaseCargoCanal::Otro, 50)->update(['activo' => false]);
        $reserva = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03', ['origen_texto' => 'Booking']);

        expect(app(UtilidadReserva::class)->de($reserva)['total_cargos'])->toBe(5000 + 2000);
    });

    it('sin canal solo resta la comisión; un bloqueo o una cancelada no tienen utilidad', function (): void {
        $reserva = ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03', ['origen_texto' => 'Teléfono', 'comision' => 1000]);
        $u = app(UtilidadReserva::class)->de($reserva);

        expect($u['canal'])->toBeNull()->and($u['bruta'])->toBe(199000)->and($u['neta'])->toBe(199000);
        $reserva->update(['estado' => EstadoReserva::Cancelada]);
        expect(app(UtilidadReserva::class)->de($reserva->refresh()))->toBeNull();
    });

    it('el informe suma por canal', function (): void {
        ($this->cargo)('ISR', ClaseCargoCanal::RetencionRenta, 4);
        ($this->reservar)($this->unidad, '2030-03-01', '2030-03-03', ['origen_texto' => 'Booking']);

        $reporte = Informes::generar('utilidad', [$this->propiedad->id], CarbonImmutable::parse('2030-03-01'), CarbonImmutable::parse('2030-03-31'));

        expect($reporte['filas'])->toHaveCount(1)->and($reporte['filas'][0][0])->toBe('Booking')->and($reporte['columnas'])->toContain(__('informes.columnas.utilidad_neta'));
    });

    it('sugiere los cargos de cada portal según el país', function (): void {
        $mexico = CargosSugeridos::de('airbnb', new Mexico());
        $espana = CargosSugeridos::de('airbnb', new Espana());
        $expedia = CargosSugeridos::de('expedia', new Mexico());

        expect($mexico['comision'])->toBe(16.0)
            ->and(array_column($mexico['cargos'], 'clase'))->toContain(ClaseCargoCanal::RetencionRenta->value, ClaseCargoCanal::RetencionIva->value)
            ->and($espana['comision'])->toBe(15.5)
            ->and($espana['cargos'])->toBe([])
            ->and($expedia['comision'])->toBeNull();
    });

    it('los cargos se guardan desde el canal', function (): void {
        Livewire::actingAs($this->admin)->test(EditCanal::class, ['record' => $this->canal->getRouteKey()])
            ->fillForm(['comision_base' => BaseCargoCanal::SinImpuestos->value, 'cargos' => [['nombre' => 'Servicio', 'clase' => ClaseCargoCanal::Servicio->value, 'calculo' => CalculoCargoCanal::Porcentaje->value, 'valor' => 3, 'base' => BaseCargoCanal::TarifaBase->value, 'activo' => true]]])
            ->call('save')->assertHasNoFormErrors();

        expect($this->canal->refresh()->comision_base)->toBe(BaseCargoCanal::SinImpuestos)
            ->and(CanalCargo::query()->where('canal_id', $this->canal->id)->firstOrFail()->base)->toBe(BaseCargoCanal::TarifaBase);
    });
});
