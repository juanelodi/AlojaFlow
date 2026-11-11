<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Comprobantes\ComprobanteRechazado;
use App\Comprobantes\GestorComprobantes;
use App\Comprobantes\ImagenComprobante;
use App\Enums\EstadoComprobante;
use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPrecioGrupo;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Enums\TipoDocumentoLegal;
use App\Filament\Pages\SeguimientoGrupos;
use App\Filament\Resources\Comprobantes\ComprobantePagoResource;
use App\Filament\Resources\Comprobantes\Pages\ListComprobantes;
use App\Filament\Resources\Grupos\GrupoResource;
use App\Filament\Resources\Grupos\Pages\CreateGrupo;
use App\Filament\Resources\Grupos\Pages\EditGrupo;
use App\Filament\Resources\Grupos\Pages\ViewGrupo;
use App\Filament\Resources\Grupos\RelationManagers\ComprobantesRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\CuotasRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\HabitacionesRelationManager;
use App\Filament\Resources\Grupos\RelationManagers\MensajesRelationManager;
use App\Grupos\AccesoCoordinador;
use App\Grupos\CalculadorPrecio;
use App\Grupos\CalendarioPagos;
use App\Grupos\ContratoGrupo;
use App\Grupos\ListaHuespedes;
use App\Llegada\GestorDocumentosLegales;
use App\Llegada\GestorLlegadas;
use App\Models\Aceptacion;
use App\Models\Archivo;
use App\Models\Aviso;
use App\Models\ComprobantePago;
use App\Models\ContactoHuesped;
use App\Models\CuotaGrupo;
use App\Models\DocumentoLegal;
use App\Models\EnlacePublico;
use App\Models\Grupo;
use App\Models\GrupoHabitacion;
use App\Models\Huesped;
use App\Models\MensajeGrupo;
use App\Models\Pago;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\GestorGrupos;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Calendario;
use App\Servicios\SeguimientoGrupos as ConsultaSeguimiento;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

afterEach(fn () => Ajustes::olvidar());

function imagenDePrueba(int $ancho = 300, int $alto = 200, string $tipo = 'jpeg'): string
{
    $imagen = imagecreatetruecolor($ancho, $alto);
    imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 15, 118, 110));
    ob_start();
    match ($tipo) {
        'png' => imagepng($imagen),
        'webp' => imagewebp($imagen),
        default => imagejpeg($imagen),
    };

    return (string) ob_get_clean();
}

function habitacionesDePrueba(array $noches, array $mas = []): array
{
    $lista = [];
    foreach ($noches as $i => $n) {
        $lista[] = ($mas[$i] ?? []) + ['id' => $i + 1, 'noches' => $n, 'capacidad' => 2, 'precio_noche' => null, 'base_tarifa' => 0];
    }

    return $lista;
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'minutos_retencion' => 30, 'instrucciones' => 'Transfiere a la cuenta 123', 'horas_revision' => 48]],
    ]);
    $this->propiedad->refresh();
    $this->ajena = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2, 'activa' => true]);
    $this->unidades = collect(range(1, 4))->map(fn (int $n) => Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => '10'.$n, 'nombre' => 'Cuarto '.$n, 'capacidad' => 2 + ($n > 2 ? 2 : 0), 'activa' => true]));
    $this->coordinador = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Prueba']);
    ContactoHuesped::query()->create(['huesped_id' => $this->coordinador->id, 'tipo' => TipoContacto::Telefono, 'valor' => '55 1111 2222', 'valor_normalizado' => '5511112222']);
    ContactoHuesped::query()->create(['huesped_id' => $this->coordinador->id, 'tipo' => TipoContacto::Correo, 'valor' => 'ana@example.com', 'valor_normalizado' => 'ana@example.com']);
    $this->admin = User::query()->create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'es_administrador' => true]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->limpieza = User::query()->create(['name' => 'Lim', 'email' => 'lim@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true]);
    Acceso::asignarRoles($this->limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $this->hoy = $this->propiedad->hoy();
    $this->gestor = app(GestorGrupos::class);
    $this->grupo = fn (array $unidades = [0, 1], int $noches = 2, EstadoReserva $estado = EstadoReserva::Confirmada): Grupo => $this->gestor->crear(
        $this->propiedad,
        'Boda Pérez',
        $this->coordinador->id,
        array_map(fn (int $i): int => $this->unidades[$i]->id, $unidades),
        $this->hoy->addDays(10),
        $this->hoy->addDays(10 + $noches),
        2,
        $estado,
    );
    $this->conAcceso = function (Grupo $grupo): array {
        $clave = app(AccesoCoordinador::class)->generarClave($grupo);
        $token = EnlacePublico::query()->where('objeto_id', $grupo->id)->where('objeto_type', $grupo->getMorphClass())->whereNull('revocado_en')->sole()->token();

        return [$token, $clave];
    };
    // Ya dentro, con su clave propia y sin nada que aceptar.
    $this->entrarComoCoordinador = function (Grupo $grupo): string {
        [$token, $clave] = ($this->conAcceso)($grupo);
        $this->post(route('grupo.entrar', ['token' => $token]), ['clave' => $clave])->assertRedirect();
        $this->post(route('grupo.clave', ['token' => $token]), ['clave' => 'mi-clave-larga', 'clave_confirmation' => 'mi-clave-larga'])->assertRedirect();

        return $token;
    };
});

describe('precio del grupo', function (): void {
    it('reparte un total por noches, con el resto en la última', function (): void {
        $calculo = CalculadorPrecio::calcular(ModoPrecioGrupo::Total, 100000, [], habitacionesDePrueba([1, 1, 1]), null, 0.0, 0);

        expect(array_column($calculo['lineas'], 'base'))->toBe([33333, 33333, 33334])
            ->and($calculo['total'])->toBe(100000);
    });

    it('cuenta por capacidad y respeta el precio propio de la habitación', function (): void {
        $calculo = CalculadorPrecio::calcular(ModoPrecioGrupo::PorCapacidad, null, [2 => 50000, 4 => 80000], habitacionesDePrueba([2, 2, 2], [
            1 => ['capacidad' => 4],
            2 => ['precio_noche' => 10000],
        ]), null, 0.0, 0);

        expect(array_column($calculo['lineas'], 'base'))->toBe([100000, 160000, 20000]);
    });

    it('regala las más baratas, una cada N', function (): void {
        $calculo = CalculadorPrecio::calcular(ModoPrecioGrupo::PorCapacidad, null, [2 => 1000, 4 => 2000], habitacionesDePrueba([1, 1, 1, 1], [
            0 => ['capacidad' => 4],
            1 => ['capacidad' => 4],
        ]), 2, 0.0, 0);

        expect($calculo['lineas'][3]['gratis'])->toBeTrue()
            ->and($calculo['lineas'][4]['gratis'])->toBeTrue()
            ->and($calculo['lineas'][1]['gratis'])->toBeFalse()
            ->and($calculo['total'])->toBe(4000);
    });

    it('sin llegar a N no regala, y N menor que 2 no significa nada', function (): void {
        $tres = habitacionesDePrueba([1, 1, 1], [0 => ['precio_noche' => 100], 1 => ['precio_noche' => 100], 2 => ['precio_noche' => 100]]);

        expect(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $tres, 5, 0.0, 0)['total'])->toBe(300)
            ->and(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $tres, 1, 0.0, 0)['total'])->toBe(300);
    });

    it('aplica el descuento a las que no son gratis y lo limita a 100', function (): void {
        $habitaciones = habitacionesDePrueba([1, 1], [0 => ['precio_noche' => 1000], 1 => ['precio_noche' => 2000]]);

        expect(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $habitaciones, null, 10.0, 0)['total'])->toBe(2700)
            ->and(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $habitaciones, null, 150.0, 0)['total'])->toBe(0)
            ->and(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $habitaciones, null, -5.0, 0)['total'])->toBe(3000)
            ->and(CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $habitaciones, 2, 10.0, 0)['total'])->toBe(1800);
    });

    it('los extras se reparten entre las que pagan y la suma siempre cuadra', function (): void {
        $habitaciones = habitacionesDePrueba([1, 1, 1], [0 => ['precio_noche' => 1000], 1 => ['precio_noche' => 2000], 2 => ['precio_noche' => 3000]]);

        $calculo = CalculadorPrecio::calcular(ModoPrecioGrupo::Tarifa, null, [], $habitaciones, 3, 0.0, 1001);

        expect($calculo['lineas'][1]['total'])->toBe(0)
            ->and(array_sum(array_column($calculo['lineas'], 'total')))->toBe($calculo['total'])
            ->and($calculo['total'])->toBe(5000 + 1001);
    });

    it('sin habitaciones no truena', function (): void {
        expect(CalculadorPrecio::calcular(ModoPrecioGrupo::Total, 1000, [], [], 2, 10.0, 500)['total'])->toBe(0);
    });

    it('al guardar el grupo, cada reserva queda con lo suyo', function (): void {
        $grupo = ($this->grupo)();

        Livewire::actingAs($this->recepcion)->test(EditGrupo::class, ['record' => $grupo->getRouteKey()])
            ->fillForm(['modo_precio' => ModoPrecioGrupo::Total->value, 'precio_total' => '1000.01'])
            ->call('save')
            ->assertHasNoFormErrors();

        $totales = $grupo->reservas()->orderBy('id')->pluck('total')->all();
        expect($totales)->toBe([50000, 50001])
            ->and($this->gestor->saldo($grupo->refresh())['total'])->toBe(100001);
    });

    it('guarda los precios por capacidad y los vuelve a enseñar', function (): void {
        $grupo = ($this->grupo)();

        Livewire::actingAs($this->recepcion)->test(EditGrupo::class, ['record' => $grupo->getRouteKey()])
            ->fillForm(['modo_precio' => ModoPrecioGrupo::PorCapacidad->value, 'precios_capacidad' => [['capacidad' => 2, 'precio' => '500.50']]])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($grupo->refresh()->precios_capacidad)->toBe([2 => 50050])
            ->and($grupo->reservas()->orderBy('id')->first()->total)->toBe(100100);

        Livewire::actingAs($this->recepcion)->test(EditGrupo::class, ['record' => $grupo->getRouteKey()])
            ->tap(fn ($pagina) => expect(array_values($pagina->get('data.precios_capacidad'))[0])->toMatchArray(['capacidad' => 2, 'precio' => '500.50']));
    });

    it('cancelar una habitación vuelve a repartir', function (): void {
        $grupo = ($this->grupo)();
        $grupo->update(['modo_precio' => ModoPrecioGrupo::Total, 'precio_total' => 100000]);
        $this->gestor->aplicarPrecios($grupo);
        $primera = $grupo->reservas()->orderBy('id')->first();

        $this->gestor->cancelarHabitacion($primera);

        expect($grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->sole()->total)->toBe(100000)
            ->and($this->gestor->saldo($grupo)['total'])->toBe(100000);
    });

    it('con la tarifa normal y nada más, no toca un precio puesto a mano', function (): void {
        $grupo = ($this->grupo)();
        $reserva = $grupo->reservas()->orderBy('id')->first();
        app(GestorReservas::class)->cambiarTotal($reserva, 12345);

        $this->gestor->aplicarPrecios($grupo);
        expect($reserva->refresh()->total)->toBe(12345);

        $this->gestor->aplicarPrecios($grupo, forzar: true);
        expect($reserva->refresh()->total)->toBe(200000);
    });
});

describe('estado y habitaciones', function (): void {
    it('tentativo deja las reservas sin confirmar, y confirmarlo las confirma', function (): void {
        $grupo = ($this->grupo)(estado: EstadoReserva::SinConfirmar);
        expect($grupo->estado)->toBe(EstadoGrupo::Tentativo)
            ->and($grupo->reservas()->where('estado', EstadoReserva::SinConfirmar->value)->count())->toBe(2);

        $this->gestor->cambiarEstado($grupo, EstadoGrupo::Confirmado);

        expect($grupo->refresh()->estado)->toBe(EstadoGrupo::Confirmado)
            ->and($grupo->reservas()->where('estado', EstadoReserva::Confirmada->value)->count())->toBe(2);
    });

    it('cancelar el grupo cancela sus reservas y libera las noches', function (): void {
        $grupo = ($this->grupo)();

        $this->gestor->cambiarEstado($grupo, EstadoGrupo::Cancelado);

        expect($grupo->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->count())->toBe(0);
        ($this->grupo)();
        expect(Grupo::query()->count())->toBe(2);
    });

    it('si alguien ya entró, no se cancela nada', function (): void {
        $grupo = ($this->grupo)();
        $primera = $grupo->reservas()->orderBy('id')->first();
        $primera->forceFill(['llegada' => $this->hoy, 'salida' => $this->hoy->addDay()])->save();
        app(GestorReservas::class)->registrarEntrada($primera);

        expect(fn () => $this->gestor->cambiarEstado($grupo, EstadoGrupo::Cancelado))->toThrow(ReservaRechazada::class);

        expect($grupo->refresh()->estado)->toBe(EstadoGrupo::Confirmado)
            ->and($grupo->reservas()->where('estado', EstadoReserva::Cancelada->value)->count())->toBe(0);
    });

    it('cambiar a una unidad ocupada no cambia nada', function (): void {
        $grupo = ($this->grupo)();
        [$a, $b] = $grupo->reservas()->orderBy('id')->get()->all();

        expect(fn () => $this->gestor->cambiarHabitacion($a, $b->unidad_id, $a->llegada, $a->salida))->toThrow(ReservaRechazada::class);

        expect($a->refresh()->unidad_id)->toBe($this->unidades[0]->id);
    });

    it('cada habitación puede tener sus fechas', function (): void {
        $grupo = ($this->grupo)();
        $reserva = $grupo->reservas()->orderBy('id')->first();

        $this->gestor->cambiarHabitacion($reserva, $this->unidades[2]->id, $this->hoy->addDays(9), $this->hoy->addDays(13));

        $reserva->refresh();
        expect($reserva->unidad_id)->toBe($this->unidades[2]->id)
            ->and($reserva->noches)->toBe(4)
            ->and($reserva->nochesOcupadas()->count())->toBe(4);
    });

    it('agregar una habitación se reparte con las demás', function (): void {
        $grupo = ($this->grupo)();
        $grupo->update(['modo_precio' => ModoPrecioGrupo::Total, 'precio_total' => 90000]);

        $this->gestor->agregarHabitacion($grupo, $this->unidades[2]->id, $this->hoy->addDays(10), $this->hoy->addDays(12), 2);

        expect($grupo->reservas()->pluck('total')->all())->toBe([30000, 30000, 30000])
            ->and(GrupoHabitacion::query()->where('grupo_id', $grupo->id)->count())->toBe(3);
    });

    it('copiar lleva unidades, noches y precios, pero no pagos ni lista', function (): void {
        $grupo = ($this->grupo)();
        $grupo->update(['modo_precio' => ModoPrecioGrupo::Total, 'precio_total' => 90000]);
        $reserva = $grupo->reservas()->orderBy('id')->first();
        $this->gestor->guardarHabitacion($reserva, ['responsable' => 'Luis', 'personas' => 2]);
        $this->gestor->cobrar($grupo, 1000, MetodoPago::Efectivo, $this->hoy);

        $copia = $this->gestor->copiar($grupo, $this->hoy->addDays(40), 'Boda 2');

        expect($copia->estado)->toBe(EstadoGrupo::Tentativo)
            ->and($copia->precio_total)->toBe(90000)
            ->and($copia->reservas()->count())->toBe(2)
            ->and($this->gestor->saldo($copia)['total'])->toBe(90000)
            ->and($this->gestor->saldo($copia)['pagado'])->toBe(0)
            ->and(GrupoHabitacion::query()->where('grupo_id', $copia->id)->whereNotNull('responsable')->count())->toBe(0);
    });

    it('si una no está libre, copiar no deja nada a medias', function (): void {
        $grupo = ($this->grupo)();

        expect(fn () => $this->gestor->copiar($grupo, $this->hoy->addDays(10), 'Encima'))->toThrow(ReservaRechazada::class);

        expect(Grupo::query()->count())->toBe(1);
    });

    it('la tarea libera las que no tienen responsable pasada la fecha, y avisa', function (): void {
        $grupo = ($this->grupo)();
        [$con, $sin] = $grupo->reservas()->orderBy('id')->get()->all();
        $this->gestor->guardarHabitacion($con, ['responsable' => 'Luis', 'personas' => 2]);
        $grupo->update(['fecha_limite_lista' => $this->hoy->addDay(), 'liberar_sin_responsable' => true]);

        $this->artisan('alojaflow:grupos')->assertSuccessful();
        expect($sin->refresh()->estado)->not->toBe(EstadoReserva::Cancelada);

        // El día del límite todavía se puede.
        $grupo->update(['fecha_limite_lista' => $this->hoy->format('Y-m-d')]);
        $this->artisan('alojaflow:grupos')->assertSuccessful();
        expect($sin->refresh()->estado)->not->toBe(EstadoReserva::Cancelada);

        $grupo->update(['fecha_limite_lista' => $this->hoy->subDay()->format('Y-m-d')]);
        $this->artisan('alojaflow:grupos')->assertSuccessful();

        expect($sin->refresh()->estado)->toBe(EstadoReserva::Cancelada)
            ->and($con->refresh()->estado)->toBe(EstadoReserva::Confirmada)
            ->and(Aviso::query()->where('tipo', 'grupo_habitaciones_liberadas')->sole()->mensaje())->toContain('Boda Pérez');
    });

    it('sin marcar la casilla, la tarea no libera nada', function (): void {
        $grupo = ($this->grupo)();
        $grupo->update(['fecha_limite_lista' => $this->hoy->subDay(), 'liberar_sin_responsable' => false]);

        $this->artisan('alojaflow:grupos')->assertSuccessful();

        expect($grupo->reservas()->where('estado', EstadoReserva::Cancelada->value)->count())->toBe(0);
    });

    it('el alta avisa de lo ocupado, y da de alta desde el panel', function (): void {
        ($this->grupo)([0]);

        Livewire::actingAs($this->recepcion)->test(CreateGrupo::class)
            ->fillForm([
                'propiedad_id' => $this->propiedad->id, 'nombre' => 'Otra boda', 'coordinador_id' => $this->coordinador->id,
                'estado' => EstadoReserva::SinConfirmar->value, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'),
                'personas' => 2,
            ])
            ->assertSee('Ana Prueba')
            ->fillForm(['unidades' => [$this->unidades[1]->id, $this->unidades[2]->id]])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Grupo::query()->where('nombre', 'Otra boda')->sole()->estado)->toBe(EstadoGrupo::Tentativo);
    });

    it('la lista de habitaciones cambia, cancela y reactiva', function (): void {
        $grupo = ($this->grupo)();
        $reserva = $grupo->reservas()->orderBy('id')->first();

        $pagina = Livewire::actingAs($this->recepcion)->test(HabitacionesRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('datosHabitacion', $reserva->id, ['responsable' => 'Luis Ruiz', 'personas' => 1])
            ->assertHasNoTableActionErrors();
        expect($reserva->refresh()->personas)->toBe(1)
            ->and($reserva->habitacionGrupo->responsable)->toBe('Luis Ruiz');

        $pagina->callTableAction('precioHabitacion', $reserva->id, ['precio_noche' => '700'])->assertHasNoTableActionErrors();
        expect($reserva->refresh()->total)->toBe(140000);

        $pagina->callTableAction('cancelarHabitacion', $reserva->id);
        expect($reserva->refresh()->estado)->toBe(EstadoReserva::Cancelada);

        $pagina->callTableAction('reactivarHabitacion', $reserva->id);
        expect($reserva->refresh()->estado)->toBe(EstadoReserva::Confirmada);
    });
});

describe('calendario de pagos', function (): void {
    it('reparte lo pagado en las primeras en vencer', function (): void {
        $grupo = ($this->grupo)();
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'B', 'importe' => 100000, 'vence_en' => $this->hoy->addDays(5)]);
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'A', 'importe' => 100000, 'vence_en' => $this->hoy->subDay()]);
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'C', 'importe' => 200000, 'vence_en' => $this->hoy->addDays(9)]);

        $vacio = CalendarioPagos::de($grupo);
        expect(array_column($vacio['cuotas'], 'estado'))->toBe(['atrasada', 'pendiente', 'pendiente'])
            ->and($vacio['cuadra'])->toBeTrue();

        $this->gestor->cobrar($grupo, 150000, MetodoPago::Efectivo, $this->hoy);

        $calendario = CalendarioPagos::de($grupo);
        expect(array_column($calendario['cuotas'], 'estado'))->toBe(['pagada', 'parcial', 'pendiente'])
            ->and(array_column($calendario['cuotas'], 'pagado'))->toBe([100000, 50000, 0]);
    });

    it('lo que vence hoy no está atrasado, ni siquiera tarde en la noche', function (): void {
        $grupo = ($this->grupo)();
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'Hoy', 'importe' => 1000, 'vence_en' => $this->hoy->format('Y-m-d')]);

        $this->travelTo($this->hoy->addHours(23));
        expect(CalendarioPagos::de($grupo)['cuotas'][0]['estado'])->toBe('pendiente');

        $this->travelTo($this->hoy->addDay()->addHours(1));
        expect(CalendarioPagos::de($grupo->refresh())['cuotas'][0]['estado'])->toBe('atrasada');
    });

    it('dice cuando no suma el total', function (): void {
        $grupo = ($this->grupo)();
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'A', 'importe' => 100, 'vence_en' => $this->hoy]);

        expect(CalendarioPagos::de($grupo)['cuadra'])->toBeFalse();
        Livewire::actingAs($this->recepcion)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()])->assertSee(__('grupos.cuotas.no_cuadra', ['suma' => App\Support\Formato::dinero(100, 'MXN'), 'total' => App\Support\Formato::dinero(400000, 'MXN')]));
    });

    it('genera anticipo y pagos iguales que suman el total, hasta la fecha', function (): void {
        $grupo = ($this->grupo)();

        Livewire::actingAs($this->recepcion)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()])
            ->callAction('generarCuotas', data: ['anticipo_tipo' => 'porcentaje', 'anticipo' => 30, 'fecha_anticipo' => $this->hoy->format('Y-m-d'), 'cuotas' => 3, 'hasta' => $this->hoy->addDays(9)->format('Y-m-d')])
            ->assertHasNoActionErrors();

        $cuotas = $grupo->cuotas()->orderBy('vence_en')->get();
        expect($cuotas->pluck('importe')->all())->toBe([120000, 93334, 93333, 93333])
            ->and($cuotas->sum('importe'))->toBe(400000)
            ->and($cuotas->last()->vence_en->format('Y-m-d'))->toBe($this->hoy->addDays(9)->format('Y-m-d'))
            ->and(CalendarioPagos::de($grupo)['cuadra'])->toBeTrue();
    });

    it('un anticipo mayor que el total no pide más de lo que se debe', function (): void {
        $grupo = ($this->grupo)();

        $this->gestor->generarCuotas($grupo, 999999999, $this->hoy, 2, $this->hoy->addDays(5));

        expect((int) $grupo->cuotas()->sum('importe'))->toBe(400000);
    });

    it('la lista del panel enseña el estado y deja agregar', function (): void {
        $grupo = ($this->grupo)();

        Livewire::actingAs($this->recepcion)->test(CuotasRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('agregarCuota', null, ['concepto' => 'Anticipo', 'importe' => '1000', 'vence_en' => $this->hoy->subDay()->format('Y-m-d')])
            ->assertHasNoTableActionErrors()
            ->assertSee(__('grupos.cuotas.estados.atrasada'));

        expect($grupo->cuotas()->sole()->importe)->toBe(100000);
    });
});

describe('acceso del coordinador', function (): void {
    it('sin clave no enseña nada del grupo', function (): void {
        $grupo = ($this->grupo)();
        [$token] = ($this->conAcceso)($grupo);

        $this->get(route('grupo.ver', ['token' => $token]))
            ->assertOk()
            ->assertSee(__('grupos.publico.entrar_ayuda'))
            ->assertDontSee('Cuarto 1');
    });

    it('un enlace que no existe da 404, y con una clave nueva el anterior deja de servir', function (): void {
        $grupo = ($this->grupo)();
        [$token, $clave] = ($this->conAcceso)($grupo);

        $this->get(route('grupo.ver', ['token' => str_repeat('a', 40)]))->assertNotFound();

        $nueva = app(AccesoCoordinador::class)->generarClave($grupo->refresh());

        $this->get(route('grupo.ver', ['token' => $token]))->assertNotFound();
        expect(Hash::check($clave, (string) $grupo->refresh()->clave_hash))->toBeFalse()
            ->and(Hash::check($nueva, (string) $grupo->clave_hash))->toBeTrue();
    });

    it('pide cambiar la clave al entrar, y la nueva no queda a la vista', function (): void {
        $grupo = ($this->grupo)();
        [$token, $clave] = ($this->conAcceso)($grupo);
        expect($grupo->refresh()->clave_cifrada)->toBe($clave);

        $this->post(route('grupo.entrar', ['token' => $token]), ['clave' => $clave])->assertRedirect();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee(__('grupos.publico.clave_titulo'));

        // Mientras no la cambie, no puede hacer nada más.
        $this->post(route('grupo.mensaje', ['token' => $token]), ['texto' => 'Hola'])->assertRedirect();
        expect(MensajeGrupo::query()->count())->toBe(0);

        $this->post(route('grupo.clave', ['token' => $token]), ['clave' => 'corta', 'clave_confirmation' => 'corta'])->assertSessionHasErrors('clave');
        $this->post(route('grupo.clave', ['token' => $token]), ['clave' => 'mi-clave-larga', 'clave_confirmation' => 'mi-clave-larga'])->assertRedirect();

        $grupo->refresh();
        expect($grupo->clave_cifrada)->toBeNull()
            ->and($grupo->debe_cambiar_clave)->toBeFalse()
            ->and(Hash::check('mi-clave-larga', (string) $grupo->clave_hash))->toBeTrue();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('Cuarto 1');
    });

    it('cinco errores bloquean aunque después la clave sea buena, con el mismo mensaje', function (): void {
        $grupo = ($this->grupo)();
        [$token, $clave] = ($this->conAcceso)($grupo);

        foreach (range(1, 5) as $intento) {
            $this->post(route('grupo.entrar', ['token' => $token]), ['clave' => 'mala'])->assertSessionHasErrors(['clave' => __('grupos.publico.clave_incorrecta')]);
        }
        $this->post(route('grupo.entrar', ['token' => $token]), ['clave' => $clave])->assertSessionHasErrors(['clave' => __('grupos.publico.clave_incorrecta')]);
        expect($grupo->refresh()->bloqueado_hasta?->isFuture())->toBeTrue();

        $this->travel(16)->minutes();
        $this->post(route('grupo.entrar', ['token' => $token]), ['clave' => $clave])->assertSessionHasNoErrors();
    });

    it('veinte errores por conexión bloquean la conexión, para cualquier grupo', function (): void {
        $grupo = ($this->grupo)();
        [$token, $clave] = ($this->conAcceso)($grupo);
        $grupo->refresh();
        $peticion = peticionConSesion();
        foreach (range(1, 20) as $intento) {
            app(AccesoCoordinador::class)->entrar($grupo->fresh(), 'mala', $peticion);
            Grupo::query()->whereKey($grupo->id)->update(['bloqueado_hasta' => null, 'intentos_fallidos' => 0]);
        }

        expect(app(AccesoCoordinador::class)->entrar($grupo->fresh(), $clave, $peticion))->toBeFalse();
        RateLimiter::clear('grupo-conexion:'.App\Support\ClaveCliente::de($peticion));
        expect(app(AccesoCoordinador::class)->entrar($grupo->fresh(), $clave, $peticion))->toBeTrue();
    });

    it('caduca tras la salida, se puede desactivar y no vale en un grupo cancelado', function (): void {
        $grupo = ($this->grupo)();
        [$token] = ($this->conAcceso)($grupo);
        $acceso = app(AccesoCoordinador::class);
        $grupo->refresh();

        expect($acceso->disponible($grupo))->toBeTrue();
        $grupo->update(['dias_acceso_tras_salida' => 1]);
        $this->travelTo($this->hoy->addDays(13)->addHours(18));
        expect($acceso->disponible($grupo))->toBeTrue();
        $this->travelTo($this->hoy->addDays(14)->addHours(18));
        expect($acceso->disponible($grupo->refresh()))->toBeFalse();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee(__('grupos.publico.cerrado_titulo'))->assertDontSee('Cuarto 1');
        $this->travelBack();

        $acceso->desactivar($grupo);
        expect($acceso->disponible($grupo))->toBeFalse();
        $acceso->activar($grupo);
        expect($acceso->disponible($grupo))->toBeTrue();
        $this->gestor->cambiarEstado($grupo, EstadoGrupo::Cancelado);
        expect($acceso->disponible($grupo))->toBeFalse();
    });

    it('cambiar la clave saca a quien tuviera la anterior', function (): void {
        $grupo = ($this->grupo)();
        [, $clave] = ($this->conAcceso)($grupo);
        $acceso = app(AccesoCoordinador::class);
        $otra = peticionConSesion();
        $mia = peticionConSesion();
        expect($acceso->entrar($grupo->refresh(), $clave, $otra))->toBeTrue()
            ->and($acceso->entrar($grupo->refresh(), $clave, $mia))->toBeTrue();

        $acceso->cambiarClave($grupo, 'otra-clave-larga', $mia);

        expect($acceso->estaDentro($grupo, $mia))->toBeTrue()
            ->and($acceso->estaDentro($grupo, $otra))->toBeFalse();
    });

    it('la clave no sale en arreglos ni en la auditoría', function (): void {
        $grupo = ($this->grupo)();
        ($this->conAcceso)($grupo);
        $grupo->refresh();

        expect(array_keys($grupo->toArray()))->not->toContain('clave_hash')->not->toContain('clave_cifrada');
        $auditoria = (string) json_encode(RegistroAuditoria::query()->where('modelo_tipo', 'Grupo')->get()->toArray());
        expect($auditoria)->not->toContain((string) $grupo->clave_hash)->not->toContain((string) $grupo->clave_cifrada);
    });

    it('el panel da el acceso y enseña enlace y clave', function (): void {
        $grupo = ($this->grupo)();

        Livewire::actingAs($this->recepcion)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()])
            ->callAction('darAcceso')
            ->assertActionMounted('verAcceso');

        $grupo->refresh();
        expect($grupo->acceso_activo)->toBeTrue()
            ->and($grupo->clave_cifrada)->not->toBeNull()
            ->and(app(AccesoCoordinador::class)->url($grupo))->toContain('/grupo/');
    });

    it('el módulo apagado cierra la página', function (): void {
        $grupo = ($this->grupo)();
        [$token] = ($this->conAcceso)($grupo);
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Grupos)));
        Ajustes::olvidar();

        $this->get(route('grupo.ver', ['token' => $token]))->assertNotFound();
    });
});

describe('contrato y aviso', function (): void {
    beforeEach(function (): void {
        $this->contrato = DocumentoLegal::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoDocumentoLegal::ContratoGrupo, 'idioma' => 'es', 'version' => 1, 'titulo' => 'Contrato', 'cuerpo' => 'Grupo {grupo} ({codigo}), total {total}, {habitaciones} habitaciones.']);
        app(GestorDocumentosLegales::class)->publicar($this->contrato);
    });

    it('sin aceptar no se usa la página, y al aceptar queda la prueba', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('Grupo Boda Pérez ('.$grupo->codigo.'), total $4,000.00, 2 habitaciones.')->assertDontSee('Cuarto 1');
        $this->post(route('grupo.mensaje', ['token' => $token]), ['texto' => 'Hola'])->assertRedirect();
        expect(MensajeGrupo::query()->count())->toBe(0);

        $this->post(route('grupo.aceptar', ['token' => $token]), ['nombre' => 'Ana Prueba'])->assertSessionHasErrors('acepto');
        $this->post(route('grupo.aceptar', ['token' => $token]), ['acepto' => '1', 'nombre' => 'Ana Prueba'])->assertRedirect();

        $aceptacion = Aceptacion::query()->where('grupo_id', $grupo->id)->sole();
        expect($aceptacion->firmante_nombre)->toBe('Ana Prueba')
            ->and($aceptacion->huella_documento)->toBe(hash('sha256', 'Grupo Boda Pérez ('.$grupo->codigo.'), total $4,000.00, 2 habitaciones.'))
            ->and($aceptacion->copia_archivo_id)->not->toBeNull()
            ->and($aceptacion->ip)->not->toBeNull();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('Cuarto 1');
        $this->get(route('grupo.copia', ['token' => $token, 'aceptacion' => $aceptacion->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });

    it('si cambia el texto, hay que aceptar otra vez', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);
        $this->post(route('grupo.aceptar', ['token' => $token]), ['acepto' => '1', 'nombre' => 'Ana'])->assertRedirect();
        $contrato = app(ContratoGrupo::class);
        expect($contrato->pendientes($grupo, 'es'))->toBe([]);

        $this->gestor->agregarHabitacion($grupo, $this->unidades[2]->id, $this->hoy->addDays(10), $this->hoy->addDays(12), 2);

        expect($contrato->pendientes($grupo, 'es'))->toHaveCount(1);
    });

    it('cambiar de idioma no pide aceptar otra vez lo mismo', function (): void {
        $ingles = DocumentoLegal::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoDocumentoLegal::ContratoGrupo, 'idioma' => 'en', 'version' => 1, 'titulo' => 'Contract', 'cuerpo' => 'Group {grupo}']);
        app(GestorDocumentosLegales::class)->publicar($ingles);
        $grupo = ($this->grupo)();
        $contrato = app(ContratoGrupo::class);
        $contrato->aceptar($grupo, 'Ana', 'es', null, null);

        expect($contrato->pendientes($grupo, 'en'))->toBe([]);
    });

    it('el contrato de grupo no se le pide al huésped que llega', function (): void {
        expect(app(GestorLlegadas::class)->documentosPara($this->propiedad, 'es')->pluck('tipo')->all())->not->toContain(TipoDocumentoLegal::ContratoGrupo);
    });

    it('los paquetes de país traen un contrato de partida con sus variables', function (): void {
        $this->ajena->update(['paquete_pais' => App\Enums\PaquetePais::Mexico]);
        $creados = app(GestorDocumentosLegales::class)->crearBase($this->ajena->refresh());

        expect($creados)->toBeGreaterThan(0);
        $documento = DocumentoLegal::query()->where('propiedad_id', $this->ajena->id)->where('tipo', TipoDocumentoLegal::ContratoGrupo->value)->where('idioma', 'es')->sole();
        expect($documento->cuerpo)->toContain('{grupo}')->toContain('{pagos}')->toContain($this->ajena->nombre);
    });
});

describe('página del coordinador', function (): void {
    it('guarda quién va, no deja pasar la capacidad y exige responsable', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);
        $reserva = $grupo->reservas()->orderBy('id')->first();
        $ruta = route('grupo.habitacion', ['token' => $token, 'reserva' => $reserva->id]);

        $this->post($ruta, ['personas' => 1])->assertSessionHasErrorsIn('habitacion_'.$reserva->id, 'responsable');
        $this->post($ruta, ['responsable' => 'Luis', 'personas' => 9])->assertSessionHasErrorsIn('habitacion_'.$reserva->id, 'personas');
        expect($reserva->refresh()->personas)->toBe(2);

        $this->post($ruta, ['responsable' => 'Luis Ruiz', 'personas' => 1, 'telefono' => '55 3333 4444', 'correo' => 'luis@example.com', 'acompanantes' => 'Nadie'])->assertRedirect();

        expect($reserva->refresh()->personas)->toBe(1)
            ->and($reserva->habitacionGrupo->responsable)->toBe('Luis Ruiz');
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('Luis Ruiz')->assertSee('wa.me/');
    });

    it('no puede tocar una reserva de otro grupo ni de nadie', function (): void {
        $grupo = ($this->grupo)();
        $otro = ($this->grupo)([2]);
        $ajena = $otro->reservas()->sole();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->post(route('grupo.habitacion', ['token' => $token, 'reserva' => $ajena->id]), ['responsable' => 'Intruso', 'personas' => 1])->assertNotFound();

        expect($ajena->refresh()->habitacionGrupo->responsable)->toBeNull();
    });

    it('registra un pago que no cuenta hasta que se confirma', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->post(route('grupo.pago', ['token' => $token]), [
            'importe' => '1500.50', 'fecha' => $this->hoy->format('Y-m-d'), 'referencia' => 'SPEI 123',
            'comprobante' => UploadedFile::fake()->createWithContent('pago.jpg', imagenDePrueba()),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $comprobante = ComprobantePago::query()->sole();
        expect($comprobante->estado)->toBe(EstadoComprobante::PorConfirmar)
            ->and($comprobante->importe)->toBe(150050)
            ->and($this->gestor->saldo($grupo)['pagado'])->toBe(0)
            ->and($this->gestor->saldo($grupo)['por_confirmar'])->toBe(150050)
            ->and(Aviso::query()->where('tipo', 'comprobantes_por_revisar')->whereNull('resuelto_en')->count())->toBe(1);

        $this->get(route('grupo.comprobante', ['token' => $token, 'comprobante' => $comprobante->id]))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    });

    it('rechaza un archivo que no es imagen aunque se llame .jpg', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->post(route('grupo.pago', ['token' => $token]), [
            'importe' => '100', 'fecha' => $this->hoy->format('Y-m-d'),
            'comprobante' => UploadedFile::fake()->createWithContent('pago.jpg', '<?php echo "hola";'),
        ])->assertSessionHasErrorsIn('pago', 'comprobante');

        expect(ComprobantePago::query()->count())->toBe(0);
    });

    it('no enseña el comprobante de otro grupo', function (): void {
        $otro = ($this->grupo)([2]);
        $ajeno = app(GestorComprobantes::class)->recibirDeGrupo($otro, imagenDePrueba(), 100, $this->hoy, null);
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->get(route('grupo.comprobante', ['token' => $token, 'comprobante' => $ajeno->id]))->assertNotFound();
    });

    it('se escriben con el alojamiento y se marca lo leído', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->post(route('grupo.mensaje', ['token' => $token]), ['texto' => '¿A qué hora llegamos?'])->assertRedirect();
        $mensaje = MensajeGrupo::query()->sole();
        expect($mensaje->del_coordinador)->toBeTrue()->and($mensaje->leido_en)->toBeNull();

        expect(ConsultaSeguimiento::resumen([$this->propiedad->id])['mensajes'])->toHaveCount(1);
        Livewire::actingAs($this->recepcion)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()]);
        expect($mensaje->refresh()->leido_en)->not->toBeNull();

        Livewire::actingAs($this->recepcion)->test(MensajesRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('escribir', null, ['texto' => 'A las 3'])
            ->assertHasNoTableActionErrors();
        auth()->logout();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('A las 3');
        expect(MensajeGrupo::query()->where('del_coordinador', false)->sole()->leido_en)->not->toBeNull();
    });

    it('enseña los datos para pagar y el avance', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);
        $this->gestor->cobrar($grupo, 100000, MetodoPago::Efectivo, $this->hoy);

        $this->get(route('grupo.ver', ['token' => $token]))
            ->assertSee('Transfiere a la cuenta 123')
            ->assertSee('aria-valuenow="25"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    });

    it('sale de la cuenta', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);

        $this->post(route('grupo.salir', ['token' => $token]))->assertRedirect();

        $this->get(route('grupo.ver', ['token' => $token]))->assertSee(__('grupos.publico.entrar_ayuda'));
    });
});

describe('imagen del comprobante', function (): void {
    it('vuelve a dibujar JPG, PNG y WebP como JPEG, sin lo que traían', function (): void {
        foreach (['jpeg', 'png', 'webp'] as $tipo) {
            $limpia = ImagenComprobante::limpiar(imagenDePrueba(tipo: $tipo));
            expect((new finfo(FILEINFO_MIME_TYPE))->buffer($limpia))->toBe('image/jpeg');
        }
        $conEscondido = imagenDePrueba()."\xFF\xD9SECRETO-GPS";
        expect(ImagenComprobante::limpiar($conEscondido))->not->toContain('SECRETO-GPS');
    });

    it('la deja en 2000 píxeles como máximo', function (): void {
        $medidas = getimagesizefromstring(ImagenComprobante::limpiar(imagenDePrueba(3000, 1000)));

        expect($medidas[0])->toBe(2000)->and($medidas[1])->toBe(667);
    });

    it('rechaza lo que no es imagen, lo que pesa de más y lo enorme en píxeles', function (): void {
        expect(fn () => ImagenComprobante::limpiar('<?php echo 1;'))->toThrow(ComprobanteRechazado::class, __('grupos.comprobante.error_tipo'))
            ->and(fn () => ImagenComprobante::limpiar(str_repeat('a', ImagenComprobante::BYTES_MAXIMOS + 1)))->toThrow(ComprobanteRechazado::class, __('grupos.comprobante.error_tamano'))
            ->and(fn () => ImagenComprobante::limpiar(pngDeclarando(20000, 20000)))->toThrow(ComprobanteRechazado::class, __('grupos.comprobante.error_imagen'));
    });
});

describe('revisar comprobantes', function (): void {
    it('confirmar reparte el pago entre las reservas del grupo y quita el aviso', function (): void {
        $grupo = ($this->grupo)();
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 250000, $this->hoy, 'SPEI');

        Livewire::actingAs($this->recepcion)->test(ComprobantesRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('confirmarComprobante', $comprobante->id)
            ->assertHasNoTableActionErrors();

        $comprobante->refresh();
        expect($comprobante->estado)->toBe(EstadoComprobante::Confirmado)
            ->and($comprobante->revisado_por)->toBe($this->recepcion->id)
            ->and($this->gestor->saldo($grupo)['pagado'])->toBe(250000)
            ->and($this->gestor->saldo($grupo)['por_confirmar'])->toBe(0)
            ->and(Pago::query()->count())->toBe(2)
            ->and(Pago::query()->first()->metodo)->toBe(MetodoPago::Transferencia)
            ->and(Aviso::query()->where('tipo', 'comprobantes_por_revisar')->whereNull('resuelto_en')->count())->toBe(0);
    });

    it('confirmar dos veces no cobra dos veces', function (): void {
        $grupo = ($this->grupo)();
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);

        app(GestorComprobantes::class)->confirmar($comprobante);
        app(GestorComprobantes::class)->confirmar($comprobante);

        expect(Pago::query()->count())->toBe(1);
    });

    it('rechazar pide motivo, no cobra y el coordinador lo ve', function (): void {
        $grupo = ($this->grupo)();
        $token = ($this->entrarComoCoordinador)($grupo);
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);

        Livewire::actingAs($this->recepcion)->test(ComprobantesRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('rechazarComprobante', $comprobante->id, ['motivo' => ''])->assertHasTableActionErrors(['motivo']);
        Livewire::actingAs($this->recepcion)->test(ComprobantesRelationManager::class, ['ownerRecord' => $grupo, 'pageClass' => ViewGrupo::class])
            ->callTableAction('rechazarComprobante', $comprobante->id, ['motivo' => 'No llegó'])->assertHasNoTableActionErrors();

        expect($comprobante->refresh()->estado)->toBe(EstadoComprobante::Rechazado)
            ->and($this->gestor->saldo($grupo)['pagado'])->toBe(0);
        auth()->logout();
        $this->get(route('grupo.ver', ['token' => $token]))->assertSee('No llegó');
    });

    it('sin grupos ni motor no sale la lista', function (): void {
        Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => ! in_array($m, [Modulo::Grupos, Modulo::Motor, Modulo::Pagos], true))));
        Ajustes::olvidar();

        $this->actingAs($this->admin);
        expect(ComprobantePagoResource::canViewAny())->toBeFalse();
    });

    it('la lista general es de quien tiene el permiso y solo de sus propiedades', function (): void {
        $grupo = ($this->grupo)();
        $mio = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);
        $huesped = Huesped::query()->create(['propiedad_id' => $this->ajena->id, 'nombre' => 'Otro']);
        $tarifa = Tarifa::query()->create(['propiedad_id' => $this->ajena->id, 'nombre' => 'X', 'precio_noche' => 1000, 'personas_incluidas' => 2, 'activa' => true]);
        $unidad = Unidad::query()->create(['propiedad_id' => $this->ajena->id, 'tarifa_id' => $tarifa->id, 'codigo' => '1', 'nombre' => 'U', 'capacidad' => 2, 'activa' => true]);
        $reservaAjena = app(GestorReservas::class)->crear($this->ajena, ['unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'llegada' => $this->hoy->addDay()->format('Y-m-d'), 'salida' => $this->hoy->addDays(2)->format('Y-m-d'), 'personas' => 1, 'estado' => 'confirmada', 'total' => 1000]);
        $ajeno = app(GestorComprobantes::class)->recibirDeReserva($reservaAjena, imagenDePrueba(), 1000, $this->hoy, null);

        $this->actingAs($this->recepcion);
        expect(ComprobantePagoResource::getNavigationBadge())->toBe('1');
        Livewire::actingAs($this->recepcion)->test(ListComprobantes::class)
            ->assertCanSeeTableRecords([$mio])
            ->assertCanNotSeeTableRecords([$ajeno]);

        Livewire::actingAs($this->limpieza)->test(ListComprobantes::class)->assertForbidden();
    });

    it('la imagen la ve recepción', function (): void {
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo(($this->grupo)(), imagenDePrueba(), 1000, $this->hoy, null);

        $this->actingAs($this->recepcion)->get(route('archivos.ver', $comprobante->archivo_id))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    });

    it('la imagen no la ve quien solo limpia', function (): void {
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo(($this->grupo)(), imagenDePrueba(), 1000, $this->hoy, null);

        $this->actingAs($this->limpieza)->get(route('archivos.ver', $comprobante->archivo_id))->assertNotFound();
    });

    it('la imagen se guarda cifrada', function (): void {
        $grupo = ($this->grupo)();
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);

        expect($comprobante->archivo->cifrado)->toBeTrue()
            ->and($comprobante->archivo->conservar_hasta)->not->toBeNull();
    });
});

describe('comprobante en la página de la reserva', function (): void {
    beforeEach(function (): void {
        $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Luz', 'apellido1' => 'Mar']);
        ContactoHuesped::query()->create(['huesped_id' => $this->huesped->id, 'tipo' => TipoContacto::Correo, 'valor' => 'luz@example.com', 'valor_normalizado' => 'luz@example.com']);
        $this->reserva = app(GestorReservas::class)->crear($this->propiedad, [
            'unidad_id' => $this->unidades[3]->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays(5)->format('Y-m-d'), 'salida' => $this->hoy->addDays(7)->format('Y-m-d'),
            'personas' => 2, 'estado' => EstadoReserva::SinConfirmar->value, 'total' => 200000, 'expira_en' => now('UTC')->addHours(2),
        ]);
        $this->pagina = fn (): string => route('motor.reserva', ['codigo' => $this->propiedad->codigo, 'publico' => $this->reserva->codigo_publico]);
        $this->subir = fn (string $contenido, array $mas = []) => $this->post(route('motor.comprobante', ['codigo' => $this->propiedad->codigo, 'publico' => $this->reserva->codigo_publico]), $mas + [
            'importe' => '500', 'fecha' => $this->hoy->format('Y-m-d'), 'comprobante' => UploadedFile::fake()->createWithContent('pago.jpg', $contenido),
        ]);
    });

    it('la página ofrece subirlo si se dijo cómo pagar', function (): void {
        $this->get(($this->pagina)())->assertOk()->assertSee(__('grupos.comprobante.titulo'));

        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true]]]);
        Ajustes::olvidar();
        $this->get(($this->pagina)())->assertOk()->assertDontSee(__('grupos.comprobante.titulo'));
    });

    it('al subirlo, la reserva deja de caducar y queda por revisar', function (): void {
        ($this->subir)(imagenDePrueba())->assertRedirect();

        $comprobante = $this->reserva->comprobantes()->sole();
        expect($comprobante->estado)->toBe(EstadoComprobante::PorConfirmar)
            ->and($comprobante->importe)->toBe(50000)
            ->and($this->reserva->refresh()->expira_en)->toBeNull()
            ->and($this->reserva->pagado)->toBe(0);
        $this->artisan('alojaflow:reservas-vencidas')->assertSuccessful();
        expect($this->reserva->refresh()->estado)->toBe(EstadoReserva::SinConfirmar);
    });

    it('con un archivo malo le dice qué pasó, y no aparta nada', function (): void {
        ($this->subir)('no soy una imagen')->assertRedirectContains('aviso=comprobante');

        expect($this->reserva->comprobantes()->count())->toBe(0)
            ->and($this->reserva->refresh()->expira_en)->not->toBeNull();
    });

    it('confirmarlo registra el pago y confirma la reserva', function (): void {
        ($this->subir)(imagenDePrueba());
        $comprobante = $this->reserva->comprobantes()->sole();

        app(GestorComprobantes::class)->confirmar($comprobante);

        $this->reserva->refresh();
        expect($this->reserva->pagado)->toBe(50000)
            ->and($this->reserva->estado)->toBe(EstadoReserva::Confirmada);
    });

    it('rechazarlo le da otra vez su caducidad y le avisa por correo', function (): void {
        ($this->subir)(imagenDePrueba());
        $comprobante = $this->reserva->comprobantes()->sole();

        app(GestorComprobantes::class)->rechazar($comprobante, 'No se ve el importe');

        $this->reserva->refresh();
        expect($this->reserva->estado)->toBe(EstadoReserva::SinConfirmar)
            ->and($this->reserva->expira_en)->not->toBeNull()
            ->and($this->reserva->expira_en->isFuture())->toBeTrue()
            ->and($this->reserva->pagado)->toBe(0);
        $envio = App\Models\Envio::query()->where('reserva_id', $this->reserva->id)->sole();
        expect($envio->destino)->toBe('luz@example.com')->and($envio->cuerpo)->toContain('No se ve el importe');
        $this->get(($this->pagina)())->assertSee('No se ve el importe');
    });

    it('si hay otro por revisar, rechazar uno no le pone caducidad', function (): void {
        ($this->subir)(imagenDePrueba());
        ($this->subir)(imagenDePrueba());
        $primero = $this->reserva->comprobantes()->orderBy('id')->first();

        app(GestorComprobantes::class)->rechazar($primero, 'Duplicado');

        expect($this->reserva->refresh()->expira_en)->toBeNull();
    });

    it('con una reserva cancelada no se reciben', function (): void {
        app(GestorReservas::class)->cancelar($this->reserva);

        ($this->subir)(imagenDePrueba())->assertRedirectContains('aviso=comprobante_imagen');

        expect($this->reserva->comprobantes()->count())->toBe(0);
    });

    it('sale en la reserva del panel para revisarlo', function (): void {
        ($this->subir)(imagenDePrueba());
        $comprobante = $this->reserva->comprobantes()->sole();

        Livewire::actingAs($this->recepcion)->test(App\Filament\Resources\Reservas\RelationManagers\ComprobantesRelationManager::class, ['ownerRecord' => $this->reserva, 'pageClass' => App\Filament\Resources\Reservas\Pages\ViewReserva::class])
            ->assertCanSeeTableRecords([$comprobante])
            ->callTableAction('confirmarComprobante', $comprobante->id)
            ->assertHasNoTableActionErrors();

        expect($this->reserva->refresh()->pagado)->toBe(50000);
    });

    it('el motor deja apagarlo', function (): void {
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123', 'acepta_comprobantes' => false]]]);
        Ajustes::olvidar();

        $this->get(($this->pagina)())->assertDontSee(__('grupos.comprobante.titulo'));
        ($this->subir)(imagenDePrueba())->assertNotFound();
    });
});

describe('lista de huéspedes', function (): void {
    it('se descarga y se vuelve a cargar', function (): void {
        $grupo = ($this->grupo)();
        $reservas = $grupo->reservas()->orderBy('id')->get();
        $this->gestor->guardarHabitacion($reservas[0], ['responsable' => '=Luis', 'personas' => 2]);
        $lista = app(ListaHuespedes::class);

        $csv = $lista->exportar($grupo);
        expect($csv)->toStartWith("\xFF\xFE")
            ->and(App\Support\Csv::enUtf8($csv))->toContain("'=Luis")->toContain("\t")->not->toContain(';');

        $resultado = $lista->importar($grupo, $csv);
        expect($resultado['errores'])->toBe([])
            ->and($resultado['guardadas'])->toBe(2)
            ->and($reservas[0]->habitacionGrupo()->sole()->responsable)->toBe('=Luis');
    });

    it('pegado de Excel, guarda lo bueno y dice qué salió mal', function (): void {
        $grupo = ($this->grupo)();
        $otro = ($this->grupo)([2])->reservas()->sole();
        [$a, $b] = $grupo->reservas()->orderBy('id')->get()->all();
        $texto = implode("\n", [
            implode("\t", ['Reserva', 'Unidad', 'Llegada', 'Salida', 'Responsable', 'Teléfono', 'Correo', 'Personas', 'Acompañantes']),
            implode("\t", [$a->numero, 'x', 'x', 'x', 'Luis', '555', 'luis@example.com', '1', 'Nadie']),
            implode("\t", [$b->numero, 'x', 'x', 'x', 'Mar', '', 'no-es-correo', '1', '']),
            implode("\t", [$otro->numero, 'x', 'x', 'x', 'Intruso', '', '', '1', '']),
            implode("\t", [$b->numero, 'x', 'x', 'x', 'Mucha gente', '', '', '99', '']),
        ]);

        $resultado = app(ListaHuespedes::class)->importar($grupo, $texto);

        expect($resultado['guardadas'])->toBe(1)
            ->and($resultado['errores'])->toHaveCount(3)
            ->and($a->refresh()->personas)->toBe(1)
            ->and($otro->habitacionGrupo()->sole()->responsable)->toBeNull();
    });

    it('el panel descarga la lista y el PDF, e importa', function (): void {
        $grupo = ($this->grupo)();
        $a = $grupo->reservas()->orderBy('id')->first();
        $pagina = Livewire::actingAs($this->recepcion)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()]);

        $pagina->callAction('exportarLista')->assertFileDownloaded(null, null, App\Support\Csv::TIPO_EXCEL);
        $pagina->callAction('listaPdf')->assertFileDownloaded();
        $pagina->callAction('importarLista', data: ['texto' => $a->numero.';x;x;x;Luis;;;1;'])->assertHasNoActionErrors();

        expect($a->habitacionGrupo()->sole()->responsable)->toBe('Luis');
    });
});

describe('panel', function (): void {
    it('el seguimiento junta lo que hay que atender', function (): void {
        $grupo = ($this->grupo)();
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'Anticipo', 'importe' => 1000, 'vence_en' => $this->hoy->subDay()]);
        CuotaGrupo::query()->create(['grupo_id' => $grupo->id, 'concepto' => 'Lejana', 'importe' => 1000, 'vence_en' => $this->hoy->addDays(30)]);
        app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);
        $this->gestor->guardarHabitacion($grupo->reservas()->orderBy('id')->first(), ['responsable' => 'Luis', 'personas' => 2]);

        $resumen = ConsultaSeguimiento::resumen([$this->propiedad->id]);

        expect($resumen['comprobantes'])->toHaveCount(1)
            ->and($resumen['cuotas'])->toHaveCount(1)
            ->and($resumen['cuotas'][0]['estado'])->toBe('atrasada')
            ->and($resumen['cuotas'][0]['whatsapp'])->toContain('wa.me/')
            ->and($resumen['listas'][0]['faltan'])->toBe(1)
            ->and($resumen['llegadas'])->toBe([]);

        Livewire::actingAs($this->recepcion)->test(SeguimientoGrupos::class)->assertOk()->assertSee('Boda Pérez');
        Livewire::actingAs($this->limpieza)->test(SeguimientoGrupos::class)->assertForbidden();
    });

    it('quien solo ve lo suyo no entra a grupos', function (): void {
        $grupo = ($this->grupo)();
        Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion', 'solo_propias' => true]]);
        Acceso::olvidar();

        $this->actingAs($this->recepcion);
        expect(GrupoResource::canView($grupo))->toBeFalse();
        Livewire::actingAs($this->recepcion)->test(SeguimientoGrupos::class)->assertForbidden();
    });

    it('borrar solo un grupo cancelado, y solo quien administra', function (): void {
        $grupo = ($this->grupo)();
        $this->actingAs($this->admin);
        expect(GrupoResource::canDelete($grupo))->toBeFalse();
        $this->gestor->cambiarEstado($grupo, EstadoGrupo::Cancelado);
        expect(GrupoResource::canDelete($grupo->refresh()))->toBeTrue();

        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);
        $archivoId = $comprobante->archivo_id;

        Livewire::actingAs($this->admin)->test(ViewGrupo::class, ['record' => $grupo->getRouteKey()])->callAction('borrarGrupo');

        expect(Grupo::query()->whereKey($grupo->id)->exists())->toBeFalse()
            ->and(Archivo::query()->whereKey($archivoId)->exists())->toBeFalse()
            ->and(Aviso::query()->where('tipo', 'comprobantes_por_revisar')->whereNull('resuelto_en')->count())->toBe(0);
    });

    it('a recepción no le deja borrar', function (): void {
        $grupo = ($this->grupo)();
        $this->gestor->cambiarEstado($grupo, EstadoGrupo::Cancelado);

        $this->actingAs($this->recepcion);
        expect(GrupoResource::canDelete($grupo->refresh()))->toBeFalse();
    });

    it('el calendario marca las reservas del grupo con su color y dice quién va', function (): void {
        $grupo = ($this->grupo)();
        $grupo->update(['color' => '#7c3aed']);
        $this->gestor->guardarHabitacion($grupo->reservas()->orderBy('id')->first(), ['responsable' => 'Luis Ruiz', 'personas' => 2]);

        $datos = Calendario::mes([$this->propiedad->id], $this->hoy->addDays(10)->startOfMonth());
        $barras = collect($datos['filas'])->flatMap(fn (array $fila): array => $fila['barras']);

        expect($barras->filter(fn (array $b): bool => $b['grupo']?->id === $grupo->id))->toHaveCount(2);
        Livewire::actingAs($this->recepcion)->test(App\Filament\Pages\Calendario::class, ['mes' => $this->hoy->addDays(10)->format('Y-m')])
            ->assertSeeHtml('--af-grupo: #7c3aed')
            ->assertSee('Luis Ruiz');
    });
});

describe('privacidad', function (): void {
    it('anonimizar al coordinador se lleva su lista, sus mensajes y sus comprobantes', function (): void {
        $grupo = ($this->grupo)();
        ($this->conAcceso)($grupo);
        $this->gestor->guardarHabitacion($grupo->reservas()->orderBy('id')->first(), ['responsable' => 'Luis', 'telefono' => '555', 'personas' => 2]);
        MensajeGrupo::query()->create(['grupo_id' => $grupo->id, 'del_coordinador' => true, 'texto' => 'Soy Ana']);
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, 'Ana paga');
        $archivoId = $comprobante->archivo_id;

        app(GestorPrivacidad::class)->anonimizar($this->coordinador->refresh());

        $grupo->refresh();
        expect($grupo->acceso_activo)->toBeFalse()
            ->and($grupo->clave_hash)->toBeNull()
            ->and($grupo->mensajes()->count())->toBe(0)
            ->and(GrupoHabitacion::query()->where('grupo_id', $grupo->id)->whereNotNull('responsable')->count())->toBe(0)
            ->and($comprobante->refresh()->referencia)->toBeNull()
            ->and(Archivo::query()->whereKey($archivoId)->exists())->toBeFalse()
            ->and($comprobante->importe)->toBe(1000);
    });

    it('exporta lo que subió y lo que escribió como coordinador', function (): void {
        $grupo = ($this->grupo)();
        MensajeGrupo::query()->create(['grupo_id' => $grupo->id, 'del_coordinador' => true, 'texto' => 'Soy Ana']);
        app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, 'Ref-99');

        $archivo = app(GestorPrivacidad::class)->exportar($this->coordinador->refresh());

        $datos = json_decode(app(App\Archivos\GestorArchivos::class)->contenido($archivo), true);
        expect($datos['grupos'][0]['codigo'])->toBe($grupo->codigo)
            ->and($datos['grupos'][0]['mensajes'][0]['texto'])->toBe('Soy Ana')
            ->and($datos['grupos'][0]['comprobantes'][0]['referencia'])->toBe('Ref-99');
    });

    it('pasado el plazo se borran la lista, los mensajes y las imágenes; las reservas se quedan', function (): void {
        $grupo = ($this->grupo)();
        ($this->conAcceso)($grupo);
        $this->gestor->guardarHabitacion($grupo->reservas()->orderBy('id')->first(), ['responsable' => 'Luis', 'personas' => 2]);
        MensajeGrupo::query()->create(['grupo_id' => $grupo->id, 'del_coordinador' => true, 'texto' => 'Hola']);
        $comprobante = app(GestorComprobantes::class)->recibirDeGrupo($grupo, imagenDePrueba(), 1000, $this->hoy, null);
        $archivoId = $comprobante->archivo_id;

        $this->travel(23)->months();
        app(GestorPrivacidad::class)->aplicarPlazos();
        expect(Archivo::query()->whereKey($archivoId)->exists())->toBeTrue()
            ->and(MensajeGrupo::query()->count())->toBe(1);

        $this->travel(3)->months();
        $resultado = app(GestorPrivacidad::class)->aplicarPlazos();

        expect($resultado['grupos'])->toBe(1)
            ->and(Archivo::query()->whereKey($archivoId)->exists())->toBeFalse()
            ->and($comprobante->refresh()->archivo_id)->toBeNull()
            ->and(MensajeGrupo::query()->count())->toBe(0)
            ->and(GrupoHabitacion::query()->whereNotNull('responsable')->count())->toBe(0)
            ->and($grupo->refresh()->acceso_activo)->toBeFalse()
            ->and($grupo->reservas()->count())->toBe(2);
    });
});

function peticionConSesion(): Illuminate\Http\Request
{
    $peticion = Illuminate\Http\Request::create('/');
    $peticion->setLaravelSession(new Illuminate\Session\Store('prueba', new Illuminate\Session\ArraySessionHandler(10)));

    return $peticion;
}

function pngDeclarando(int $ancho, int $alto): string
{
    $chunk = fn (string $tipo, string $datos): string => pack('N', strlen($datos)).$tipo.$datos.pack('N', crc32($tipo.$datos));

    return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $ancho, $alto, 8, 2, 0, 0, 0)).$chunk('IDAT', (string) gzcompress(str_repeat("\0", 10))).$chunk('IEND', '');
}
