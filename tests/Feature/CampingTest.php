<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Camping\AjustesCamping;
use App\Camping\AtributosLugar;
use App\Camping\CupoExtras;
use App\Camping\ErrorLectura;
use App\Camping\FiltroLugares;
use App\Camping\GestorLecturas;
use App\Enums\BaseCargo;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use App\Enums\ServicioMedido;
use App\Enums\TipoDocumentoLegal;
use App\Enums\TipoLugar;
use App\Filament\Pages\Lecturas as PaginaLecturas;
use App\Filament\Pages\Residentes as PaginaResidentes;
use App\Filament\Resources\Reservas\Pages\EditReserva;
use App\Models\Aceptacion;
use App\Models\CargoReserva;
use App\Models\ConceptoCargo;
use App\Models\CuotaReserva;
use App\Models\DocumentoLegal;
use App\Models\Huesped;
use App\Models\Lectura;
use App\Models\Plano;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Modulos\Modulo;
use App\Modulos\Modulos;
use App\Motor\MotorReservas;
use App\Privacidad\GestorPrivacidad;
use App\Reservas\Cotizador;
use App\Reservas\GestorReservas;
use App\Reservas\Mensualidades;
use App\Reservas\ReservaRechazada;
use App\Seguridad\Acceso;
use App\Servicios\Residentes;
use App\Support\Ajustes;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function imagenDelPlano(): string
{
    $imagen = imagecreatetruecolor(300, 200);
    imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, 200, 220, 200));
    ob_start();
    imagepng($imagen);

    return (string) ob_get_clean();
}

beforeEach(function (): void {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');
    Ajustes::olvidar();
    Modulos::guardar(Modulo::cases());
    $this->propiedad = Propiedad::query()->create([
        'nombre' => 'Camping Río', 'codigo' => 'rio', 'modo' => ModoPropiedad::Nativa, 'paquete_pais' => PaquetePais::Mexico, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'idioma' => 'es',
        'ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123']],
    ])->refresh();
    $this->tarifa = Tarifa::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Parcela', 'precio_noche' => 50000, 'personas_incluidas' => 4]);
    $this->lugar = fn (string $codigo, array $atributos = []): Unidad => Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'tarifa_id' => $this->tarifa->id, 'codigo' => $codigo, 'nombre' => 'Parcela '.$codigo, 'capacidad' => 6, 'activa' => true, 'atributos' => $atributos ?: null]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'apellido1' => 'Ejemplo']);
    $this->hoy = $this->propiedad->hoy();
    $this->reservar = fn (Unidad $unidad, int $desde = 0, int $hasta = 40, int $total = 2000000, array $mas = []): Reserva => app(GestorReservas::class)->crear($this->propiedad, $mas + [
        'unidad_id' => $unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => $total,
    ]);
    $this->gerente = User::query()->create(['name' => 'Ger', 'email' => 'ger@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);
    $this->recepcion = User::query()->create(['name' => 'Rec', 'email' => 'rec@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($this->recepcion, [['propiedad_id' => $this->propiedad->id, 'rol' => 'recepcion']]);
    $this->portero = User::query()->create(['name' => 'Otro', 'email' => 'otro@example.com', 'password' => Hash::make('contrasena-de-prueba'), 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($this->gerente);
    $this->sinCamping = fn () => Modulos::guardar(array_values(array_filter(Modulo::cases(), fn (Modulo $m): bool => $m !== Modulo::Camping)));
    $this->motor = fn (array $mas = []): string => '/reservar/rio?'.http_build_query($mas + ['llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]);
    $this->datosReserva = fn (array $mas = []): array => $mas + ['tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2, 'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '5512345678', 'comentario' => null, 'cupon' => null, 'cargos' => [], 'idioma' => 'es'];
});

afterEach(fn () => Ajustes::olvidar());

describe('lo que tiene cada lugar', function (): void {
    it('dice lo que tiene, y en Estados Unidos, en pies', function (): void {
        $unidad = ($this->lugar)('1', ['tipo' => 'vehiculo', 'luz' => true, 'amperes' => 30, 'agua' => true, 'acceso' => 'atravesado', 'largo_maximo' => 12, 'mascotas' => false]);

        expect(AtributosLugar::etiquetas($unidad))->toBe(['Casa rodante o camper', 'Luz 30 A', 'Agua', 'Entrada de paso', 'Hasta 12 m de largo']);

        $this->propiedad->update(['paquete_pais' => PaquetePais::EstadosUnidos]);
        expect(AtributosLugar::unidadLargo($this->propiedad->refresh()))->toBe('ft');
    });

    it('de varios lugares, solo promete lo que tienen todos', function (): void {
        $uno = ($this->lugar)('1', ['luz' => true, 'agua' => true]);
        $dos = ($this->lugar)('2', ['luz' => true, 'drenaje' => true]);

        expect(AtributosLugar::comunes(collect([$uno, $dos])))->toBe(['Luz']);
    });

    it('el formulario de la unidad los guarda', function (): void {
        $unidad = ($this->lugar)('1');

        Livewire::test(App\Filament\Resources\Unidades\Pages\EditUnidad::class, ['record' => $unidad->id])
            ->assertFormFieldExists('atributos.tipo')
            ->fillForm(['atributos.tipo' => 'tienda', 'atributos.luz' => true, 'atributos.amperes' => 15, 'atributos.largo_maximo' => '8.5'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($unidad->refresh()->atributo('tipo'))->toBe('tienda')
            ->and((int) $unidad->atributo('amperes'))->toBe(15)
            ->and($unidad->atributo('luz'))->toBeTrue();
    });

    it('con el módulo apagado no se ven', function (): void {
        $unidad = ($this->lugar)('1');
        ($this->sinCamping)();

        Livewire::test(App\Filament\Resources\Unidades\Pages\EditUnidad::class, ['record' => $unidad->id])
            ->assertDontSee(__('camping.atributos.titulo'));
    });
});

describe('filtros del motor', function (): void {
    beforeEach(function (): void {
        $this->conLuz = ($this->lugar)('1', ['tipo' => 'vehiculo', 'luz' => true, 'agua' => true, 'largo_maximo' => 10]);
        $this->sinLuz = ($this->lugar)('2', ['tipo' => 'tienda', 'mascotas' => true]);
    });

    it('filtra por tipo, por lo que tiene y por el largo del equipo', function (): void {
        expect((new FiltroLugares(TipoLugar::Tienda))->cumple($this->sinLuz))->toBeTrue()
            ->and((new FiltroLugares(TipoLugar::Tienda))->cumple($this->conLuz))->toBeFalse()
            ->and((new FiltroLugares(null, ['luz', 'agua']))->cumple($this->conLuz))->toBeTrue()
            ->and((new FiltroLugares(null, ['luz']))->cumple($this->sinLuz))->toBeFalse()
            ->and((new FiltroLugares(null, [], 12.0))->cumple($this->conLuz))->toBeFalse()
            ->and((new FiltroLugares(null, [], 10.0))->cumple($this->conLuz))->toBeTrue();
    });

    it('un lugar que no dice su largo no se descarta', function (): void {
        expect((new FiltroLugares(null, [], 50.0))->cumple($this->sinLuz))->toBeTrue();
    });

    it('lo que no se entiende en la dirección no filtra', function (): void {
        $filtro = FiltroLugares::desde(['tipo_lugar' => 'cosa', 'largo' => 'mucho', 'con_nada' => 1, 'con_luz' => '1']);

        expect($filtro->tipo)->toBeNull()
            ->and($filtro->largo)->toBeNull()
            ->and($filtro->casillas)->toBe(['luz'])
            ->and($filtro->parametros())->toBe(['con_luz' => 1]);
    });

    it('el motor enseña los filtros y aplica lo que se pidió', function (): void {
        $this->get(($this->motor)())->assertOk()->assertSee(__('camping.motor.filtros'))->assertSee('Parcela');

        $this->get(($this->motor)(['con_drenaje' => 1]))->assertOk()->assertSee(__('motor.sin_resultados'));
        $this->get(($this->motor)(['con_luz' => 1]))->assertOk()->assertSee(__('camping.motor.filtros'))->assertDontSee(__('motor.sin_resultados'));
    });

    it('sin lugares con datos, el motor no enseña filtros; con el módulo apagado, tampoco los aplica', function (): void {
        Unidad::query()->update(['atributos' => null]);
        $this->get(($this->motor)())->assertOk()->assertDontSee(__('camping.motor.filtros'));

        ($this->sinCamping)();
        $this->get(($this->motor)(['con_drenaje' => 1]))->assertOk()->assertDontSee(__('motor.sin_resultados'));
    });

    it('reservar respeta el filtro: no le toca un lugar en el que no cabe', function (): void {
        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['filtro' => new FiltroLugares(null, ['mascotas'])]));

        expect($reserva->unidad_id)->toBe($this->sinLuz->id);

        expect(fn () => app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['correo' => 'otra@example.com', 'filtro' => new FiltroLugares(null, ['mascotas'])])))
            ->toThrow(ReservaRechazada::class, __('motor.error.sin_lugar'));
    });
});

describe('equipo del huésped', function (): void {
    beforeEach(function (): void {
        $this->unidad = ($this->lugar)('1', ['largo_maximo' => 10]);
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123', 'pide_equipo' => true]]]);
    });

    it('lo pregunta el motor y queda cifrado en la reserva', function (): void {
        $this->get('/reservar/rio/datos?'.http_build_query(['tarifa' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]))
            ->assertOk()->assertSee('name="placas"', false);

        $this->withHeader('Origin', (string) config('app.url'))->post('/reservar/rio', [
            'tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2,
            'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '5512345678', 'acepto' => 1,
            'tipo_equipo' => 'remolque', 'largo' => '8', 'vehiculos' => 1, 'placas' => 'ABC-123',
        ])->assertRedirect();

        $reserva = Reserva::query()->sole();
        expect($reserva->equipo)->toEqual(['tipo' => 'remolque', 'largo' => '8', 'vehiculos' => 1, 'placas' => 'ABC-123']);

        $crudo = (string) DB::table('reservas')->where('id', $reserva->id)->value('equipo');
        expect($crudo)->not->toContain('ABC-123');
    });

    it('si su equipo no cabe en ningún lugar, se lo dice y no reserva', function (): void {
        $this->withHeader('Origin', (string) config('app.url'))->post('/reservar/rio', [
            'tarifa_id' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2,
            'nombre' => 'Ana', 'apellido' => 'Ejemplo', 'correo' => 'ana@example.com', 'telefono' => '5512345678', 'acepto' => 1, 'largo' => '15',
        ]);

        expect(Reserva::query()->count())->toBe(0);
    });

    it('sin el ajuste no lo pregunta', function (): void {
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123']]]);

        $this->get('/reservar/rio/datos?'.http_build_query(['tarifa' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]))
            ->assertOk()->assertDontSee('name="placas"', false);
    });

    it('en el panel se captura y avisa si no cabe', function (): void {
        $reserva = ($this->reservar)($this->unidad, 5, 8, 150000);

        Livewire::test(EditReserva::class, ['record' => $reserva->id])
            ->fillForm(['equipo.tipo' => 'camper', 'equipo.largo' => '15', 'equipo.vehiculos' => 2, 'equipo.placas' => 'XYZ-1'])
            ->assertSee(__('camping.equipo.no_cabe', ['lugar' => 'Parcela 1', 'maximo' => 10, 'unidad' => 'm']))
            ->call('save')
            ->assertHasNoFormErrors();

        expect($reserva->refresh()->equipo['placas'])->toBe('XYZ-1');
    });

    it('se ve en la reserva, también para un usuario sin administrador', function (): void {
        $reserva = ($this->reservar)($this->unidad, 5, 8, 150000, ['equipo' => ['placas' => 'XYZ-1']]);

        Livewire::actingAs($this->recepcion)->test(App\Filament\Resources\Reservas\Pages\ViewReserva::class, ['record' => $reserva->id])
            ->assertSee('XYZ-1');
    });

    it('sale en la copia de sus datos y se borra al anonimizar', function (): void {
        $reserva = ($this->reservar)($this->unidad, -400, -395, 150000, ['equipo' => ['placas' => 'XYZ-1']]);
        $privacidad = app(GestorPrivacidad::class);

        $copia = app(GestorArchivos::class)->contenido($privacidad->exportar($this->huesped));
        expect(str_contains($copia, 'XYZ-1'))->toBeTrue();

        $privacidad->anonimizar($this->huesped->refresh());
        expect($reserva->refresh()->equipo)->toBeNull();
    });
});

describe('elegir el lugar en el plano', function (): void {
    beforeEach(function (): void {
        $this->uno = ($this->lugar)('A1', ['luz' => true]);
        $this->dos = ($this->lugar)('A2');
        $archivo = app(GestorArchivos::class)->guardar(imagenDelPlano(), 'plano.png', 'image/png', ClaseArchivo::Plano, null, $this->propiedad->id);
        $this->plano = Plano::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Zona del río', 'archivo_id' => $archivo->id]);
        $this->plano->posiciones()->create(['unidad_id' => $this->uno->id, 'x' => 10, 'y' => 20]);
        $this->plano->posiciones()->create(['unidad_id' => $this->dos->id, 'x' => 50, 'y' => 60]);
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123', 'elegir_lugar' => true]]]);
    });

    it('enseña el plano con lo libre, y de lo ocupado no dice quién está', function (): void {
        ($this->reservar)($this->dos, 9, 13, 100000);

        $html = (string) $this->get(($this->motor)())->assertOk()->assertSee('Zona del río')->getContent();

        expect($html)->toContain('class="lugar libre"')
            ->and($html)->toContain('class="lugar ocupado"')
            ->and($html)->toContain('unidad='.$this->uno->id)
            ->and($html)->not->toContain('unidad='.$this->dos->id)
            ->and($html)->not->toContain('Ana');
    });

    it('apagado, no enseña el plano ni lo sirve', function (): void {
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123']]]);

        $this->get(($this->motor)())->assertOk()->assertDontSee('Zona del río');
        $this->get('/reservar/rio/plano/'.$this->plano->id)->assertNotFound();
    });

    it('sirve la imagen del plano sin sesión, y no la de otra propiedad', function (): void {
        auth()->logout();
        $respuesta = $this->get('/reservar/rio/plano/'.$this->plano->id)->assertOk()->assertHeader('Content-Type', 'image/png');
        expect($respuesta->getContent())->toBe(imagenDelPlano());

        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajeno = Plano::query()->create(['propiedad_id' => $otra->id, 'nombre' => 'Ajeno', 'archivo_id' => $this->plano->archivo_id]);
        $this->get('/reservar/rio/plano/'.$ajeno->id)->assertNotFound();
    });

    it('un SVG no se sirve: puede llevar código', function (): void {
        $archivo = app(GestorArchivos::class)->guardar('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'plano.svg', 'image/svg+xml', ClaseArchivo::Plano, null, $this->propiedad->id);
        $this->plano->update(['archivo_id' => $archivo->id]);

        $this->get('/reservar/rio/plano/'.$this->plano->id)->assertNotFound();
    });

    it('reserva justo ese lugar; si se ocupó, se lo dice y no le da otro', function (): void {
        $datos = ($this->datosReserva)(['unidad_id' => $this->dos->id]);
        $reserva = app(MotorReservas::class)->reservar($this->propiedad, $datos);
        expect($reserva->unidad_id)->toBe($this->dos->id);

        expect(fn () => app(MotorReservas::class)->reservar($this->propiedad, ['correo' => 'otra@example.com'] + $datos))
            ->toThrow(ReservaRechazada::class, __('camping.motor.lugar_ocupado'));
    });

    it('con el ajuste apagado, no se puede pedir un lugar concreto', function (): void {
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123']]]);

        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['unidad_id' => $this->dos->id]));

        expect($reserva->unidad_id)->toBe($this->uno->id);
    });

    it('con cargo por elegir, se suma solo a quien elige, y no se ofrece aparte', function (): void {
        $cargo = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Elegir mi lugar', 'base' => BaseCargo::PorEstancia, 'importe' => 20000, 'visible_motor' => true]);
        $this->propiedad->update(['ajustes' => ['motor' => ['activo' => true, 'instrucciones' => 'Cuenta 123', 'elegir_lugar' => true, 'cargo_lugar_id' => $cargo->id]]]);
        $url = '/reservar/rio/datos?'.http_build_query(['tarifa' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]);

        $this->get($url)->assertOk()->assertDontSee('Elegir mi lugar');
        $this->get($url.'&unidad='.$this->dos->id)->assertOk()
            ->assertSee('Elegir mi lugar')
            ->assertSee('Parcela A2');

        $elige = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['unidad_id' => $this->dos->id]));
        expect($elige->total)->toBe(120000)
            ->and($elige->cargos()->sole()->nombre)->toBe('Elegir mi lugar');

        $noElige = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['correo' => 'otra@example.com']));
        expect($noElige->total)->toBe(100000);
    });

    it('si el lugar del enlace ya no está libre, vuelve a buscar', function (): void {
        ($this->reservar)($this->dos, 9, 13, 100000);

        $this->get('/reservar/rio/datos?'.http_build_query(['tarifa' => $this->tarifa->id, 'unidad' => $this->dos->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]))
            ->assertRedirect();
    });

    it('los ajustes del motor lo guardan', function (): void {
        $cargo = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Elegir', 'base' => BaseCargo::PorEstancia, 'importe' => 100]);

        Livewire::test(App\Filament\Pages\MotorDeReservas::class)
            ->fillForm(['pide_equipo' => true, 'elegir_lugar' => true, 'cargo_lugar_id' => $cargo->id])
            ->call('guardar')
            ->assertHasNoFormErrors();

        $ajustes = new App\Motor\AjustesMotor($this->propiedad->refresh());
        expect($ajustes->pideEquipo())->toBeTrue()
            ->and($ajustes->elegirLugar())->toBeTrue()
            ->and($ajustes->cargoLugarId())->toBe($cargo->id);
    });
});

describe('lecturas', function (): void {
    beforeEach(function (): void {
        $this->unidad = ($this->lugar)('1');
        (new AjustesCamping($this->propiedad))->guardarTarifas(['luz' => '3.5', 'agua' => '20']);
        $this->propiedad->refresh();
        $this->lecturas = app(GestorLecturas::class);
        $this->leer = fn (int $dias, string|float|int $actual, array $mas = []): int => $this->lecturas->registrar($this->propiedad, ServicioMedido::Luz, $this->hoy->addDays($dias), [$this->unidad->id => ['actual' => $actual] + $mas]);
    });

    it('la primera lectura es el punto de partida; la siguiente se cobra a quien está', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);

        expect(($this->leer)(-30, 100))->toBe(1);
        $primera = Lectura::query()->sole();
        expect($primera->inicial)->toBeTrue()
            ->and($primera->importe)->toBe(0)
            ->and($reserva->refresh()->total)->toBe(2000000);

        ($this->leer)(0, 220.5);

        $segunda = Lectura::query()->latest('id')->first();
        expect((float) $segunda->consumo)->toBe(120.5)
            ->and($segunda->importe)->toBe(42175)
            ->and($segunda->reserva_id)->toBe($reserva->id)
            ->and($reserva->refresh()->total)->toBe(2042175)
            ->and($reserva->cargos()->sole()->importe)->toBe(42175)
            ->and($reserva->cargos()->sole()->nombre)->toContain('Luz')->toContain('120.5')->toContain('kWh');
    });

    it('no le cobra a nadie lo que gastó el de antes', function (): void {
        ($this->leer)(-50, 100);
        $reserva = ($this->reservar)($this->unidad, -10, 40);

        ($this->leer)(-5, 300);

        expect(Lectura::query()->latest('id')->first()->importe)->toBe(0)
            ->and($reserva->refresh()->total)->toBe(2000000);

        ($this->leer)(0, 310);
        expect($reserva->refresh()->total)->toBe(2000000 + 3500);
    });

    it('con el lugar vacío, se anota sin cobrar', function (): void {
        ($this->leer)(-5, 100);
        ($this->leer)(0, 150);

        expect(Lectura::query()->latest('id')->first()->importe)->toBe(0)
            ->and(CargoReserva::query()->count())->toBe(0);
    });

    it('el día de la salida se le cobra todavía al que se va', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 0);
        ($this->leer)(-30, 100);

        ($this->leer)(0, 110);

        expect($reserva->refresh()->total)->toBe(2000000 + 3500);
    });

    it('sin precio se anota, y no se cobra un cero', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);
        $this->lecturas->registrar($this->propiedad, ServicioMedido::Gas, $this->hoy->subDays(10), [$this->unidad->id => ['actual' => 5]]);
        $this->lecturas->registrar($this->propiedad, ServicioMedido::Gas, $this->hoy, [$this->unidad->id => ['actual' => 9]]);

        expect(Lectura::query()->count())->toBe(2)
            ->and($reserva->cargos()->count())->toBe(0);
    });

    it('cada servicio lleva su cuenta', function (): void {
        ($this->reservar)($this->unidad, -40, 40);
        ($this->leer)(-20, 500);
        $this->lecturas->registrar($this->propiedad, ServicioMedido::Agua, $this->hoy->subDays(10), [$this->unidad->id => ['actual' => 3]]);
        $this->lecturas->registrar($this->propiedad, ServicioMedido::Agua, $this->hoy, [$this->unidad->id => ['actual' => 5]]);

        expect(Lectura::query()->where('servicio', 'agua')->latest('id')->first()->importe)->toBe(4000);
    });

    it('rechaza lo que no tiene sentido, y si una está mal no guarda ninguna', function (): void {
        $otra = ($this->lugar)('2');
        ($this->leer)(-5, 100);

        expect(fn () => ($this->leer)(0, 50))->toThrow(ErrorLectura::class, __('camping.lecturas.error_menor', ['unidad' => 'Parcela 1', 'anterior' => 100]));
        expect(fn () => ($this->leer)(0, 'mucho'))->toThrow(ErrorLectura::class, __('camping.lecturas.error_numero', ['unidad' => 'Parcela 1']));
        expect(fn () => ($this->leer)(-10, 200))->toThrow(ErrorLectura::class, __('camping.lecturas.error_fecha', ['unidad' => 'Parcela 1']));
        expect(fn () => ($this->leer)(1, 200))->toThrow(ErrorLectura::class, __('camping.lecturas.error_futura'));

        expect(fn () => $this->lecturas->registrar($this->propiedad, ServicioMedido::Luz, $this->hoy, [$otra->id => ['actual' => 5], $this->unidad->id => ['actual' => 1]]))->toThrow(ErrorLectura::class);
        expect(Lectura::query()->where('unidad_id', $otra->id)->count())->toBe(0);
    });

    it('un medidor nuevo vuelve a empezar, sin cobrar', function (): void {
        ($this->reservar)($this->unidad, -40, 40);
        ($this->leer)(-10, 900);

        ($this->leer)(0, 5, ['inicial' => true]);

        $lectura = Lectura::query()->latest('id')->first();
        expect($lectura->inicial)->toBeTrue()
            ->and($lectura->importe)->toBe(0);
    });

    it('no se capturan unidades de otra propiedad', function (): void {
        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $ajena = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'Ajena', 'capacidad' => 2, 'activa' => true]);

        expect($this->lecturas->registrar($this->propiedad, ServicioMedido::Luz, $this->hoy, [$ajena->id => ['actual' => 5]]))->toBe(0);
    });

    it('quitar la última devuelve lo cobrado; las de antes no se pueden quitar', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);
        ($this->leer)(-20, 100);
        ($this->leer)(0, 110);
        $primera = Lectura::query()->oldest('id')->first();
        $ultima = Lectura::query()->latest('id')->first();

        expect(fn () => $this->lecturas->quitar($primera))->toThrow(ErrorLectura::class, __('camping.lecturas.error_no_ultima'));

        $this->lecturas->quitar($ultima);

        expect($reserva->refresh()->total)->toBe(2000000)
            ->and($reserva->cargos()->count())->toBe(0)
            ->and(Lectura::query()->count())->toBe(1);
    });

    it('editar la reserva no se come lo de los medidores', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40, 2000000);
        ($this->leer)(-20, 100);
        ($this->leer)(0, 110);

        app(GestorReservas::class)->modificar($reserva->refresh(), [
            'unidad_id' => $this->unidad->id, 'titular_id' => $this->huesped->id, 'llegada' => $reserva->llegada->format('Y-m-d'), 'salida' => $reserva->salida->format('Y-m-d'),
            'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => $reserva->total, 'cargos' => [],
        ]);

        expect($reserva->refresh()->cargos()->count())->toBe(1);
    });

    it('recalcular la tarifa en el formulario conserva lo de los medidores', function (): void {
        $this->tarifa->update(['precio_noche' => 10000]);
        $reserva = ($this->reservar)($this->unidad, 0, 4, 40000);
        ($this->leer)(0, 100);
        ($this->leer)(0, 110);
        expect($reserva->refresh()->total)->toBe(43500);

        $pagina = Livewire::test(EditReserva::class, ['record' => $reserva->id])
            ->assertFormSet(['sin_catalogo' => 3500])
            ->set('data.salida', $this->hoy->addDays(5)->format('Y-m-d'))
            ->callAction(TestAction::make('aplicarTarifa')->schemaComponent('total', schema: 'form'));

        expect((float) $pagina->get('data.total'))->toBe(535.0);
    });

    it('con calendario de cobros va como pago aparte, y el calendario sigue cuadrando', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy->subDays(40));
        ($this->leer)(-20, 100);
        ($this->leer)(0, 110);

        $reserva->refresh();
        $cuota = $reserva->cuotas()->whereNotNull('cargo_id')->sole();
        expect($cuota->importe)->toBe(3500)
            ->and($cuota->vence_en->format('Y-m-d'))->toBe($this->hoy->format('Y-m-d'))
            ->and(Mensualidades::de($reserva)['cuadra'])->toBeTrue();

        // Generar otra vez lo respeta.
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy);
        expect($reserva->cuotas()->whereNotNull('cargo_id')->count())->toBe(1)
            ->and(Mensualidades::de($reserva->refresh())['cuadra'])->toBeTrue();

        // Y quitar la lectura se lleva su pago.
        $this->lecturas->quitar(Lectura::query()->latest('id')->first());
        expect($reserva->cuotas()->whereNotNull('cargo_id')->count())->toBe(0);
    });

    it('la pantalla captura en bloque, enseña lo que se va a cobrar y deja quitar', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);
        ($this->leer)(-20, 100);

        $pagina = Livewire::actingAs($this->recepcion)->test(PaginaLecturas::class)
            ->assertSee('Parcela 1')
            ->assertSee('Ana Ejemplo')
            ->set('capturas.'.$this->unidad->id.'.actual', '110')
            ->assertSee(Formato::dinero(3500, 'MXN'))
            ->call('guardar');

        expect($reserva->refresh()->total)->toBe(2003500);

        $pagina->call('quitar', Lectura::query()->latest('id')->first()->id);
        expect($reserva->refresh()->total)->toBe(2000000);
    });

    it('la pantalla dice lo que falló, y pone los precios', function (): void {
        ($this->leer)(-20, 100);

        Livewire::actingAs($this->gerente)->test(PaginaLecturas::class)
            ->set('capturas.'.$this->unidad->id.'.actual', '50')
            ->call('guardar')
            ->assertNotified(__('camping.lecturas.error_menor', ['unidad' => 'Parcela 1', 'anterior' => 100]))
            ->callAction('precios', ['luz' => '4.25', 'agua' => null, 'gas' => null]);

        expect((new AjustesCamping($this->propiedad->refresh()))->tarifa(ServicioMedido::Luz))->toBe('4.25')
            ->and((new AjustesCamping($this->propiedad))->tarifa(ServicioMedido::Agua))->toBeNull();
    });

    it('los precios solo los cambia quien lleva el catálogo', function (): void {
        Livewire::actingAs($this->recepcion)->test(PaginaLecturas::class)->assertActionHidden('precios');
        Livewire::actingAs($this->gerente)->test(PaginaLecturas::class)->assertActionVisible('precios');
    });

    it('un número enorme no truena: dice qué lectura está mal', function (): void {
        expect(fn () => ($this->leer)(0, '99999999999'))->toThrow(ErrorLectura::class, __('camping.lecturas.error_numero', ['unidad' => 'Parcela 1']));
    });

    it('en el calendario, lo de un medidor no se edita ni se quita: se cambia desde las lecturas', function (): void {
        $reserva = ($this->reservar)($this->unidad, -40, 40);
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy->subDays(40));
        ($this->leer)(-20, 100);
        ($this->leer)(0, 110);
        $cuota = $reserva->refresh()->cuotas()->whereNotNull('cargo_id')->sole();

        Livewire::actingAs($this->gerente)->test(App\Filament\Resources\Reservas\RelationManagers\CuotasRelationManager::class, ['ownerRecord' => $reserva, 'pageClass' => App\Filament\Resources\Reservas\Pages\ViewReserva::class])
            ->assertTableActionHidden('editarCuota', $cuota)
            ->assertTableActionHidden('quitarCuota', $cuota);
    });

    it('borrar la propiedad se lleva sus lecturas', function (): void {
        ($this->reservar)($this->unidad, -40, 40);
        ($this->leer)(-20, 100);
        ($this->leer)(0, 110);

        app(App\Propiedades\BorradorPropiedad::class)->borrar($this->propiedad);

        expect(Lectura::query()->count())->toBe(0)
            ->and(Propiedad::query()->whereKey($this->propiedad->id)->exists())->toBeFalse();
    });

    it('sin permiso, con otra propiedad o con el módulo apagado no se entra', function (): void {
        Livewire::actingAs($this->portero)->test(PaginaLecturas::class)->assertForbidden();

        $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN', 'idioma' => 'es']);
        $unidad = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'X', 'nombre' => 'Ajena', 'capacidad' => 2, 'activa' => true]);
        $ajena = Lectura::query()->create(['propiedad_id' => $otra->id, 'unidad_id' => $unidad->id, 'servicio' => ServicioMedido::Luz, 'leida_en' => $this->hoy->format('Y-m-d'), 'actual' => 1]);
        Livewire::actingAs($this->recepcion)->test(PaginaLecturas::class)->call('quitar', $ajena->id)->assertNotFound();

        ($this->sinCamping)();
        Livewire::actingAs($this->gerente)->test(PaginaLecturas::class)->assertForbidden();
    });
});

describe('temporada y residentes', function (): void {
    beforeEach(function (): void {
        $this->unidad = ($this->lugar)('1');
    });

    it('quien se queda toda la temporada paga su precio', function (): void {
        TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Verano', 'desde' => '2027-06-01', 'hasta' => '2027-08-31', 'precio_noche' => 80000, 'precio_completa' => 3000000]);
        $cotizar = fn (string $llegada, string $salida) => Cotizador::cotizar($this->unidad->refresh(), CarbonImmutable::parse($llegada), CarbonImmutable::parse($salida), 2);

        // Toda: las 92 noches, a su precio.
        $toda = $cotizar('2027-06-01', '2027-09-01');
        expect($toda->total)->toBe(3000000)
            ->and($toda->estanciaLarga)->toBe('temporada');

        // Con noches de más, esas a su precio normal.
        expect($cotizar('2027-05-30', '2027-09-02')->total)->toBe(3000000 + 3 * 50000);

        // Una parte: por noche.
        expect($cotizar('2027-06-01', '2027-06-11')->total)->toBe(800000);

        ($this->sinCamping)();
        expect($cotizar('2027-06-01', '2027-09-01')->total)->toBe(92 * 80000);
    });

    it('el motor dice que aplicó el precio de temporada', function (): void {
        $desde = $this->hoy->addDays(20);
        TemporadaTarifa::query()->create(['tarifa_id' => $this->tarifa->id, 'nombre' => 'Verano', 'desde' => $desde->format('Y-m-d'), 'hasta' => $desde->addDays(9)->format('Y-m-d'), 'precio_noche' => 80000, 'precio_completa' => 300000]);

        $this->get(($this->motor)(['llegada' => $desde->format('Y-m-d'), 'salida' => $desde->addDays(10)->format('Y-m-d')]))->assertOk()
            ->assertSee(__('estancias.motor.aplicado.temporada'));
    });

    it('junta a quien se queda un mes o más, ya aquí o por llegar', function (): void {
        $otra = ($this->lugar)('2');
        $tercera = ($this->lugar)('3');
        $cuarta = ($this->lugar)('4');
        $residente = ($this->reservar)($this->unidad, -10, 30);
        ($this->reservar)($otra, 0, 5, 1000);
        $porLlegar = ($this->reservar)($tercera, 20, 60);
        ($this->reservar)($cuarta, 60, 100);

        $filas = Residentes::lista([$this->propiedad->id]);

        expect(array_map(fn (array $f): int => $f['reserva']->id, $filas))->toBe([$residente->id, $porLlegar->id]);
    });

    it('dice si firmó el reglamento, y le pide firmar si falta', function (): void {
        $reserva = ($this->reservar)($this->unidad, -10, 30);

        expect(Residentes::lista([$this->propiedad->id])[0]['reglamento'])->toBeNull();

        $documento = DocumentoLegal::query()->create(['propiedad_id' => $this->propiedad->id, 'tipo' => TipoDocumentoLegal::Reglamento, 'idioma' => 'es', 'version' => 1, 'titulo' => 'Reglamento', 'cuerpo' => 'No hacer ruido.', 'publicado_en' => now('UTC')]);
        $fila = Residentes::lista([$this->propiedad->id])[0];
        expect($fila['reglamento'])->toBe('falta')
            ->and($fila['whatsapp'])->toContain('wa.me');

        Aceptacion::query()->create(['documento_legal_id' => $documento->id, 'propiedad_id' => $this->propiedad->id, 'reserva_id' => $reserva->id, 'firmante_nombre' => 'Ana', 'huella_documento' => str_repeat('a', 64), 'aceptada_en' => now('UTC'), 'verificacion' => App\Enums\VerificacionAceptacion::cases()[0]]);
        expect(Residentes::lista([$this->propiedad->id])[0]['reglamento'])->toBe('firmado');
    });

    it('la pantalla abre para quien lleva reservas, con lo que toca pagar', function (): void {
        $reserva = ($this->reservar)($this->unidad, -10, 30);
        $reserva->cuotas()->create(['importe' => 700000, 'vence_en' => $this->hoy->subDay()->format('Y-m-d'), 'concepto' => 'Junio']);

        Livewire::actingAs($this->recepcion)->test(PaginaResidentes::class)
            ->assertSee('Parcela 1')
            ->assertSee('Ana Ejemplo')
            ->assertSee(Formato::dinero(700000, 'MXN'));
    });

    it('sin permiso o con el módulo apagado no se entra', function (): void {
        Livewire::actingAs($this->portero)->test(PaginaResidentes::class)->assertForbidden();

        ($this->sinCamping)();
        Livewire::actingAs($this->gerente)->test(PaginaResidentes::class)->assertForbidden();
    });

    it('los mensajes por periodo se les pueden mandar a todos', function (): void {
        ($this->reservar)($this->unidad, -10, 30);
        ($this->reservar)(($this->lugar)('2'), 0, 5, 1000);
        $plantilla = App\Models\Plantilla::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'aviso', 'nombre' => 'Aviso', 'asunto' => ['es' => 'Hola'], 'cuerpo' => ['es' => 'Corte de agua el martes.'], 'activa' => true]);

        $pagina = Livewire::actingAs($this->gerente)->test(App\Filament\Pages\MensajesPeriodo::class)
            ->set('por', 'residentes')
            ->set('plantillaId', $plantilla->id);

        expect($pagina->instance()->filas())->toHaveCount(1);
    });
});

describe('extras con cupo', function (): void {
    beforeEach(function (): void {
        $this->unidad = ($this->lugar)('1');
        $this->segunda = ($this->lugar)('2');
        $this->tercera = ($this->lugar)('3');
        $this->kayak = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Kayak', 'base' => BaseCargo::PorEstancia, 'importe' => 10000, 'cupo_diario' => 3, 'visible_motor' => true]);
        $this->apartado = fn (Unidad $unidad, int $desde, int $hasta, int $cantidad): Reserva => ($this->reservar)($unidad, $desde, $hasta, 1000, ['cargos' => [['concepto_cargo_id' => $this->kayak->id, 'cantidad' => $cantidad]]]);
        $this->libres = fn (int $desde, int $hasta, ?int $sin = null): ?int => CupoExtras::libres($this->kayak, $this->hoy->addDays($desde), $this->hoy->addDays($hasta), $sin);
    });

    it('cuenta por noche, y el peor día manda', function (): void {
        expect(($this->libres)(10, 12))->toBe(3);

        ($this->apartado)($this->unidad, 10, 12, 2);
        ($this->apartado)($this->segunda, 11, 13, 1);

        expect(($this->libres)(10, 11))->toBe(1)
            ->and(($this->libres)(11, 12))->toBe(0)
            ->and(($this->libres)(12, 13))->toBe(2)
            ->and(($this->libres)(10, 13))->toBe(0)
            ->and(($this->libres)(13, 15))->toBe(3);
    });

    it('la salida no cuenta, y lo cancelado devuelve el cupo', function (): void {
        $reserva = ($this->apartado)($this->unidad, 10, 12, 3);

        expect(($this->libres)(12, 14))->toBe(3)
            ->and(($this->libres)(11, 12))->toBe(0);

        app(GestorReservas::class)->cancelar($reserva);
        expect(($this->libres)(11, 12))->toBe(3);
    });

    it('se puede excluir la propia reserva', function (): void {
        $reserva = ($this->apartado)($this->unidad, 10, 12, 2);

        expect(($this->libres)(10, 12, $reserva->id))->toBe(3);
    });

    it('un extra sin cupo no tiene límite', function (): void {
        $this->kayak->update(['cupo_diario' => null]);

        expect(($this->libres)(10, 12))->toBeNull();
    });

    it('el motor ofrece lo que queda, cobra la cantidad y no vende de más', function (): void {
        ($this->apartado)($this->unidad, 10, 12, 2);
        $url = '/reservar/rio/datos?'.http_build_query(['tarifa' => $this->tarifa->id, 'llegada' => $this->hoy->addDays(10)->format('Y-m-d'), 'salida' => $this->hoy->addDays(12)->format('Y-m-d'), 'personas' => 2]);

        $html = (string) $this->get($url)->assertOk()->assertSee('Kayak')->getContent();
        expect($html)->toContain('name="cantidades['.$this->kayak->id.']"')
            ->and($html)->toContain('<option value="1"')
            ->and($html)->not->toContain('<option value="2"');

        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['cantidades' => [$this->kayak->id => 1]]));
        expect($reserva->cargos()->sole()->importe)->toBe(10000)
            ->and($reserva->total)->toBe(110000);

        expect(fn () => app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['correo' => 'otra@example.com', 'cantidades' => [$this->kayak->id => 1]])))
            ->toThrow(ReservaRechazada::class, __('camping.extras.agotado', ['extra' => 'Kayak']));

        $this->get($url)->assertOk()->assertSee(__('camping.extras.sin_cupo'));
    });

    it('marcar la casilla también pasa por el cupo', function (): void {
        ($this->apartado)($this->unidad, 10, 12, 3);

        expect(fn () => app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['cargos' => [$this->kayak->id]])))
            ->toThrow(ReservaRechazada::class, __('camping.extras.agotado', ['extra' => 'Kayak']));
    });

    it('no se puede pedir cantidad de un extra que no está permitido', function (): void {
        $oculto = ConceptoCargo::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Oculto', 'base' => BaseCargo::PorEstancia, 'importe' => 999, 'visible_motor' => false]);

        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['cantidades' => [$oculto->id => 5]]));

        expect($reserva->cargos()->count())->toBe(0);
    });

    it('en el panel solo avisa: un caso especial lo decide quien atiende', function (): void {
        ($this->apartado)($this->unidad, 10, 12, 3);
        $reserva = ($this->reservar)($this->segunda, 10, 12, 1000);

        Livewire::test(EditReserva::class, ['record' => $reserva->id])
            ->fillForm(['cargos' => [['concepto_cargo_id' => $this->kayak->id, 'cantidad' => 1]]])
            ->assertSee(__('camping.extras.agotado', ['extra' => 'Kayak']))
            ->call('save')
            ->assertHasNoFormErrors();

        expect($reserva->refresh()->cargos()->count())->toBe(1);
    });

    it('con el módulo apagado, el cupo no cuenta', function (): void {
        ($this->apartado)($this->unidad, 10, 12, 3);
        ($this->sinCamping)();

        $reserva = app(MotorReservas::class)->reservar($this->propiedad, ($this->datosReserva)(['cargos' => [$this->kayak->id]]));

        expect($reserva->cargos()->count())->toBe(1);
    });

    it('el catálogo guarda el cupo', function (): void {
        Livewire::test(App\Filament\Resources\ConceptosCargo\Pages\EditConceptoCargo::class, ['record' => $this->kayak->id])
            ->fillForm(['cupo_diario' => 7])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->kayak->refresh()->cupo_diario)->toBe(7);
    });
});

describe('módulo', function (): void {
    it('tiene sus textos, y avisa si hay lecturas al apagarlo', function (): void {
        $unidad = ($this->lugar)('1');
        expect(Modulo::Camping->etiqueta())->toBe('Campings y parcelas')
            ->and(TipoLugar::Vehiculo->etiqueta())->toBe('Casa rodante o camper')
            ->and(ServicioMedido::Agua->etiqueta())->toBe('Agua')
            ->and(Modulos::enUso(Modulo::Camping))->toBe(0);

        app(GestorLecturas::class)->registrar($this->propiedad, ServicioMedido::Luz, $this->hoy, [$unidad->id => ['actual' => 1]]);
        expect(Modulos::enUso(Modulo::Camping))->toBe(1);
    });

    it('la demostración trae con qué probarlo', function (): void {
        (new App\Demo\GeneradorDemo())->crear();

        expect(Unidad::query()->whereNotNull('atributos')->count())->toBeGreaterThan(0)
            ->and(ConceptoCargo::query()->whereNotNull('cupo_diario')->count())->toBe(1)
            ->and(CuotaReserva::query()->count())->toBeGreaterThan(0);
    });

    it('los pagos de un residente siguen cuadrando con lo pagado', function (): void {
        $reserva = ($this->reservar)(($this->lugar)('1'), -40, 40);
        app(Mensualidades::class)->generar($reserva, Mensualidades::MES, $this->hoy->subDays(40));
        app(GestorReservas::class)->registrarPago($reserva, 1000000, MetodoPago::Efectivo, $this->hoy);

        expect(Mensualidades::de($reserva->refresh())['pagado'])->toBe(1000000);
    });
});
