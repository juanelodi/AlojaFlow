<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Demo\GeneradorDemo;
use App\Demo\ModoDemo;
use App\Enums\EstadoReserva;
use App\Enums\MetodoPago;
use App\Enums\ModoPropiedad;
use App\Enums\NivelAviso;
use App\Enums\OrigenFila;
use App\Enums\TipoReserva;
use App\Filament\Pages\Actualizaciones;
use App\Filament\Pages\CopiasSeguridad;
use App\Filament\Pages\EditarPerfil;
use App\Filament\Pages\EstadoSistema;
use App\Filament\Pages\IniciarSesion;
use App\Filament\Resources\Propiedades\Pages\EditPropiedad;
use App\Filament\Resources\Propiedades\PropiedadResource;
use App\Filament\Resources\Usuarios\UsuarioResource;
use App\Models\Aviso;
use App\Models\Huesped;
use App\Models\Principal;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\Unidad;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Propiedades\ErrorIndependizar;
use App\Propiedades\Independizador;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Ajustes::olvidar();
});

afterEach(fn () => Ajustes::olvidar());

function subordinadaConDatos(): Propiedad
{
    $propiedad = Propiedad::query()->create(['nombre' => 'Hotel', 'codigo' => 'hotel', 'modo' => ModoPropiedad::Subordinada, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $principal = Principal::query()->forceCreate(['propiedad_id' => $propiedad->id, 'url_base' => 'https://hd.example.test', 'usuario' => 'u', 'contrasena' => 'x']);
    $unidad = Unidad::query()->forceCreate(['propiedad_id' => $propiedad->id, 'codigo' => '1', 'nombre' => 'Uno', 'sincronizada' => true, 'origen' => OrigenFila::HotelDruid, 'principal_id' => $principal->id, 'clave_origen' => 'u1']);
    $huesped = Huesped::query()->forceCreate(['propiedad_id' => $propiedad->id, 'nombre' => 'Ana', 'sincronizada' => true, 'origen' => OrigenFila::HotelDruid, 'principal_id' => $principal->id, 'clave_origen' => 'h1']);
    reservaSincronizada($propiedad, $unidad, $huesped, '57', '2030-01-10', '2030-01-12', 50000, 'r1');
    reservaSincronizada($propiedad, $unidad, $huesped, '58', '2030-01-20', '2030-01-21', 0, 'r2');

    return $propiedad->refresh();
}

function reservaSincronizada(Propiedad $propiedad, Unidad $unidad, Huesped $huesped, string $numero, string $llegada, string $salida, int $pagado, string $clave): Reserva
{
    $reserva = Reserva::query()->forceCreate([
        'propiedad_id' => $propiedad->id, 'unidad_id' => $unidad->id, 'titular_id' => $huesped->id, 'tipo' => TipoReserva::Reserva, 'estado' => EstadoReserva::Confirmada,
        'numero' => $numero, 'codigo_publico' => substr(md5($clave), 0, 10), 'llegada' => $llegada, 'salida' => $salida, 'moneda' => 'MXN', 'total' => 100000, 'pagado' => $pagado,
        'sincronizada' => true, 'origen' => OrigenFila::HotelDruid, 'principal_id' => $unidad->principal_id, 'clave_origen' => $clave,
    ]);
    $inicio = CarbonImmutable::parse($llegada);
    $cuantas = (int) $inicio->diffInDays(CarbonImmutable::parse($salida));
    for ($i = 0; $i < $cuantas; $i++) {
        DB::table('reservas_noches')->insert(['reserva_id' => $reserva->id, 'propiedad_id' => $propiedad->id, 'unidad_id' => $unidad->id, 'fecha' => $inicio->addDays($i)->format('Y-m-d'), 'importe' => 1000, 'exclusiva' => null]);
    }

    return $reserva;
}

describe('pasar a independiente', function () {
    it('convierte la propiedad, con copia antes y conservando lo pagado', function () {
        $this->mock(GestorCopias::class)->shouldReceive('crear')->once()->with('antes-de-independizar')->andReturn([]);
        $propiedad = subordinadaConDatos();
        $principalId = $propiedad->principal->id;
        Aviso::abrir($propiedad->id, 'sincronizacion_pausada', NivelAviso::Error);

        expect(app(Independizador::class)->independizar($propiedad))->toBe(2);

        $propiedad->refresh();
        expect($propiedad->modo)->toBe(ModoPropiedad::Nativa)
            ->and($propiedad->consecutivo_reserva)->toBe(58)
            ->and(Principal::query()->find($principalId))->toBeNull()
            ->and(Reserva::query()->where('sincronizada', true)->count())->toBe(0)
            ->and(Unidad::query()->where('sincronizada', true)->count())->toBe(0)
            ->and(Huesped::query()->where('sincronizada', true)->count())->toBe(0)
            ->and(DB::table('reservas_noches')->whereNull('exclusiva')->count())->toBe(0)
            ->and(Aviso::query()->whereNotNull('clave_unica')->count())->toBe(0);

        $pagada = Reserva::query()->where('numero', '57')->firstOrFail();
        expect($pagada->pagos()->count())->toBe(1)->and($pagada->pagos()->first()->importe)->toBe(50000)
            ->and(Reserva::query()->where('numero', '58')->firstOrFail()->pagos()->count())->toBe(0);

        // Ya se trabaja como cualquier otra independiente.
        $gestor = new GestorReservas(registrar: false);
        $gestor->registrarPago($pagada, 10000, MetodoPago::Efectivo, CarbonImmutable::parse('2030-01-10'));
        expect($pagada->refresh()->pagado)->toBe(60000);

        $datos = ['unidad_id' => $pagada->unidad_id, 'titular_id' => $pagada->titular_id, 'llegada' => '2030-01-10', 'salida' => '2030-01-11', 'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000];
        expect(fn () => $gestor->crear($propiedad, $datos))->toThrow(ReservaRechazada::class);

        $nueva = $gestor->crear($propiedad, ['llegada' => '2030-02-10', 'salida' => '2030-02-11'] + $datos);
        expect($nueva->numero)->toBe('59');
    });

    it('se niega si hay dobles reservas y dice cuáles', function () {
        $this->mock(GestorCopias::class)->shouldNotReceive('crear');
        $propiedad = subordinadaConDatos();
        reservaSincronizada($propiedad, Unidad::query()->firstOrFail(), Huesped::query()->firstOrFail(), '60', '2030-01-11', '2030-01-13', 0, 'r3');

        expect(fn () => app(Independizador::class)->independizar($propiedad))
            ->toThrow(ErrorIndependizar::class, 'Uno · 2030-01-11 (57, 60)');
        expect($propiedad->refresh()->modo)->toBe(ModoPropiedad::Subordinada)
            ->and(Reserva::query()->where('sincronizada', true)->count())->toBe(3);
    });

    it('no toca nada si no se pudo guardar la copia', function () {
        $this->mock(GestorCopias::class)->shouldReceive('crear')->andThrow(new ErrorCopia('sin espacio'));
        $propiedad = subordinadaConDatos();

        expect(fn () => app(Independizador::class)->independizar($propiedad))->toThrow(ErrorCopia::class);
        expect($propiedad->refresh()->modo)->toBe(ModoPropiedad::Subordinada);
    });

    it('desde el panel pide escribir el código', function () {
        $this->mock(GestorCopias::class)->shouldReceive('crear')->once()->andReturn([]);
        $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
        $propiedad = subordinadaConDatos();

        Livewire::test(EditPropiedad::class, ['record' => $propiedad->getRouteKey()])
            ->callAction('independizar', ['confirmacion' => 'otro'])
            ->assertHasActionErrors(['confirmacion']);
        expect($propiedad->refresh()->esIndependiente())->toBeFalse();

        Livewire::test(EditPropiedad::class, ['record' => $propiedad->getRouteKey()])
            ->callAction('independizar', ['confirmacion' => 'hotel'])
            ->assertHasNoActionErrors()
            ->assertNotified(__('ui.independizar.listo', ['reservas' => 2]));
        expect($propiedad->refresh()->esIndependiente())->toBeTrue();
    });
});

describe('demostración pública', function () {
    it('apagada, todo sigue igual', function () {
        $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));

        expect(ModoDemo::activo())->toBeFalse()
            ->and(UsuarioResource::canViewAny())->toBeTrue()
            ->and(CopiasSeguridad::canAccess())->toBeTrue();
        $this->artisan('alojaflow:demo-reiniciar')->assertFailed();
        $this->get('/login')->assertDontSee(__('ui.modo_demo.banner'));
    });

    it('bloquea usuarios, actualizaciones, copias, estado y contraseñas', function () {
        config(['alojaflow.modo_demo' => true]);
        $usuario = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es', 'email' => ModoDemo::CORREO, 'password' => ModoDemo::CONTRASENA]);
        $this->actingAs($usuario);

        expect(UsuarioResource::canViewAny())->toBeFalse()
            ->and(Actualizaciones::canAccess())->toBeFalse()
            ->and(CopiasSeguridad::canAccess())->toBeFalse()
            ->and(EstadoSistema::canAccess())->toBeFalse()
            ->and(PropiedadResource::canCreate())->toBeFalse();
        $this->get('/usuarios')->assertForbidden();
        $this->get('/copias-de-seguridad')->assertForbidden();

        Livewire::test(EditarPerfil::class)
            ->fillForm(['email' => 'otro@example.com', 'password' => 'una-contrasena-nueva', 'idioma' => 'en'])
            ->call('save');
        $usuario->refresh();
        expect($usuario->email)->toBe(ModoDemo::CORREO)
            ->and(Hash::check(ModoDemo::CONTRASENA, $usuario->password))->toBeTrue()
            ->and($usuario->idioma)->toBe('en');
    });

    it('el inicio de sesión viene lleno y muestra el aviso', function () {
        config(['alojaflow.modo_demo' => true]);

        Livewire::test(IniciarSesion::class)->assertFormSet(['email' => ModoDemo::CORREO, 'password' => ModoDemo::CONTRASENA]);
        expect(TareasPorVisita::tareas())->toHaveKey('alojaflow:demo-reiniciar --si-toca')
            ->not->toHaveKey('alojaflow:copia --programada');
    });

    it('el reinicio deja la base como nueva y una sola vez al día', function () {
        config(['alojaflow.modo_demo' => true]);
        // RefreshDatabase abre una transacción que el borrado de tablas cerraría.
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        try {
            Propiedad::query()->create(['nombre' => 'Basura', 'codigo' => 'basura', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);

            $this->artisan('alojaflow:demo-reiniciar')->assertSuccessful();

            expect(Propiedad::query()->where('codigo', 'basura')->exists())->toBeFalse()
                ->and(GeneradorDemo::existente())->not->toBeNull()
                ->and(User::query()->where('email', ModoDemo::CORREO)->where('es_administrador', true)->exists())->toBeTrue();

            // Sin esto, a medianoche ya es otro día y sí toca.
            $this->freezeTime();
            Propiedad::query()->create(['nombre' => 'Basura', 'codigo' => 'basura', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
            $this->artisan('alojaflow:demo-reiniciar --si-toca')->assertSuccessful();
            expect(Propiedad::query()->where('codigo', 'basura')->exists())->toBeTrue();
        } finally {
            // Las demás pruebas esperan una base vacía.
            Artisan::call('migrate:fresh', ['--force' => true]);
        }
    });
});
