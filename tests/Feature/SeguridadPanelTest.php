<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Filament\Pages\Limpieza;
use App\Filament\Resources\Canales\Pages\CreateCanal;
use App\Filament\Resources\Canales\Pages\EditCanal;
use App\Filament\Resources\FuentesIcal\Pages\CreateFuenteIcal;
use App\Filament\Resources\Reservas\AccionesReserva;
use App\Filament\Resources\Tareas\Pages\CreateTarea;
use App\Models\Archivo;
use App\Models\Canal;
use App\Models\ContactoHuesped;
use App\Models\FuenteIcal;
use App\Models\Huesped;
use App\Models\Politica;
use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    Storage::fake('local');

    $crear = function (string $nombre): array {
        $propiedad = Propiedad::query()->create(['nombre' => $nombre, 'codigo' => mb_strtolower($nombre), 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN']);
        $tarifa = Tarifa::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Doble', 'precio_noche' => 100000, 'personas_incluidas' => 2]);
        $unidad = Unidad::query()->create(['propiedad_id' => $propiedad->id, 'tarifa_id' => $tarifa->id, 'codigo' => $nombre.'-1', 'nombre' => $nombre.'-1', 'capacidad' => 2, 'orden' => 1]);
        $huesped = Huesped::query()->create(['propiedad_id' => $propiedad->id, 'nombre' => 'Huesped', 'apellido1' => $nombre]);
        ContactoHuesped::query()->create(['huesped_id' => $huesped->id, 'tipo' => TipoContacto::Telefono, 'valor' => '+52 55 1234 5678', 'valor_normalizado' => '+525512345678', 'orden' => 0]);

        return [$propiedad, $unidad, $huesped];
    };
    [$this->lago, $this->unidadLago, $this->huespedLago] = $crear('Lago');
    [$this->playa, $this->unidadPlaya, $this->huespedPlaya] = $crear('Playa');

    $this->usuario = function (string $rol, ?Propiedad $propiedad = null): User {
        $usuario = User::factory()->create(['activo' => true]);
        Acceso::asignarRoles($usuario, [['propiedad_id' => ($propiedad ?? $this->lago)->id, 'rol' => $rol]]);

        return $usuario;
    };
    $hoy = CarbonImmutable::parse($this->lago->hoy()->format('Y-m-d'));
    $this->reserva = app(GestorReservas::class)->crear($this->lago, [
        'unidad_id' => $this->unidadLago->id, 'titular_id' => $this->huespedLago->id, 'llegada' => $hoy->format('Y-m-d'), 'salida' => $hoy->addDays(2)->format('Y-m-d'),
        'personas' => 2, 'estado' => EstadoReserva::Confirmada->value, 'total' => 200000, 'cargos' => [],
    ]);
});

describe('limpieza y mantenimiento', function () {
    it('no ven teléfonos, saldos ni fichas; sí lo justo para su trabajo', function (string $rol) {
        $usuario = ($this->usuario)($rol);

        $this->actingAs($usuario)->get('/hoy')->assertOk()->assertSee('Lago-1')
            ->assertDontSee('5512345678')->assertDontSee('$2,000.00')->assertDontSee('tel:', false);
        $this->get('/')->assertOk()->assertDontSee(__('ui.resumen.pendiente'));
        $this->get('/calendario')->assertOk()->assertDontSee('/reservas/'.$this->reserva->id, false);
        $this->get('/reservas')->assertForbidden();
        $this->get('/reservas/'.$this->reserva->id)->assertNotFound();
        $this->get('/cierre-del-dia')->assertForbidden();
        $this->get('/choques')->assertForbidden();
    })->with(['limpieza', 'mantenimiento']);

    it('recepción sigue viendo todo', function () {
        $this->actingAs(($this->usuario)('recepcion'))->get('/hoy')->assertOk()->assertSee('tel:', false)->assertSee('/reservas/'.$this->reserva->id, false);
        $this->get('/reservas/'.$this->reserva->id)->assertOk();
    });

    it('no pueden marcar no-show: cancelaría la reserva', function () {
        $limpieza = ($this->usuario)('limpieza');
        $this->actingAs($limpieza);
        expect(AccionesReserva::noShow()->record($this->reserva)->isVisible())->toBeFalse();

        $this->actingAs(($this->usuario)('recepcion'));
        expect(AccionesReserva::noShow()->record($this->reserva)->isVisible())->toBeTrue();
    });
});

describe('datos entre propiedades', function () {
    it('un gerente no puede apuntar un calendario iCal a una unidad de otra propiedad', function () {
        $this->actingAs(($this->usuario)('gerente'));

        Livewire::test(CreateFuenteIcal::class)->fillForm([
            'propiedad_id' => $this->lago->id, 'unidad_id' => $this->unidadPlaya->id, 'nombre' => 'Airbnb',
            'url' => 'https://www.airbnb.example/calendar.ics', 'frecuencia_minutos' => 30, 'activa' => true,
        ])->call('create');

        expect(FuenteIcal::query()->where('unidad_id', $this->unidadPlaya->id)->count())->toBe(0);
    });

    it('no se puede asignar una política de otra propiedad a un canal', function () {
        $this->actingAs(($this->usuario)('gerente'));
        $ajena = Politica::query()->create(['propiedad_id' => $this->playa->id, 'nombre' => 'Ajena', 'codigo' => 'ajena']);
        $canal = Canal::query()->create(['propiedad_id' => $this->lago->id, 'nombre' => 'Booking', 'activo' => true]);

        // Al crear y al editar: el formulario solo ofrece las suyas, pero lo que llega lo manda el navegador.
        Livewire::test(CreateCanal::class)->set('data.propiedad_id', $this->lago->id)->set('data.nombre', 'Otro')->set('data.politica_id', $ajena->id)->call('create');
        expect(Canal::query()->where('nombre', 'Otro')->count())->toBe(0);

        Livewire::test(EditCanal::class, ['record' => $canal->getKey()])->set('data.propiedad_id', $this->playa->id)->set('data.politica_id', $ajena->id)->call('save');
        expect($canal->refresh()->politica_id)->toBeNull();
    });

    it('no se puede asignar una tarea a quien no trabaja en esa propiedad', function () {
        $this->actingAs(($this->usuario)('gerente'));
        $ajeno = ($this->usuario)('limpieza', $this->playa);

        Livewire::test(CreateTarea::class)->set('data.propiedad_id', $this->lago->id)->set('data.titulo', 'Pintar')->set('data.tipo', 'mantenimiento')->set('data.fecha', '2026-12-01')->set('data.asignada_a', $ajeno->id)->call('create');

        expect(App\Models\Tarea::query()->where('asignada_a', $ajeno->id)->count())->toBe(0);
    });

    it('cambiar la propiedad en el formulario no deja leer los huéspedes de otra', function () {
        $this->actingAs(($this->usuario)('recepcion'));

        // El buscador del campo devuelve vacío: la propiedad elegida no es de este usuario.
        expect(Acceso::propiedadElegida($this->playa->id, App\Filament\Resources\Reservas\ReservaResource::propiedadesPermitidas()))->toBe(0)
            ->and(Acceso::propiedadElegida($this->lago->id, App\Filament\Resources\Reservas\ReservaResource::propiedadesPermitidas()))->toBe($this->lago->id)
            ->and(Acceso::propiedadElegida('1 or 1=1', [1]))->toBe(0)
            ->and(Acceso::propiedadElegida(['x'], [1]))->toBe(0);
    });
});

describe('archivos subidos', function () {
    it('no toma ni borra un archivo fuera de la carpeta de subidas', function () {
        Storage::disk('local')->put('archivos/1/privado.pdf', 'secreto');
        $gestor = app(GestorArchivos::class);

        expect(fn () => $gestor->guardarSubida('archivos/1/privado.pdf', ClaseArchivo::FotoTarea, null, $this->lago->id))->toThrow(InvalidArgumentException::class);
        expect(fn () => $gestor->guardarSubida(GestorArchivos::CARPETA_SUBIDAS.'/../archivos/1/privado.pdf', ClaseArchivo::FotoTarea, null, $this->lago->id))->toThrow(InvalidArgumentException::class);

        expect(Storage::disk('local')->exists('archivos/1/privado.pdf'))->toBeTrue()->and(Archivo::query()->count())->toBe(0);
    });

    it('el formulario de incidencias rechaza una ruta inventada por el navegador', function () {
        Storage::disk('local')->put('archivos/1/privado.pdf', 'secreto');
        $this->actingAs(($this->usuario)('limpieza'));

        Livewire::test(Limpieza::class)->callAction(
            'incidencia',
            data: ['titulo' => 'Fuga', 'fotos' => ['archivos/1/privado.pdf']],
            arguments: ['unidad' => $this->unidadLago->id],
        );

        expect(Storage::disk('local')->exists('archivos/1/privado.pdf'))->toBeTrue()->and(Archivo::query()->count())->toBe(0);
    });

    it('un archivo sin permiso responde igual que uno que no existe', function () {
        $archivo = app(GestorArchivos::class)->guardar('contenido', 'foto.png', 'image/png', ClaseArchivo::FotoTarea, null, $this->lago->id);
        $this->actingAs(($this->usuario)('limpieza', $this->playa));

        $this->get('/archivos/'.$archivo->id)->assertNotFound();
        $this->get('/archivos/999999')->assertNotFound();
    });
});

describe('sesiones', function () {
    it('un usuario desactivado ya no llega a las rutas fuera del panel', function () {
        $admin = User::factory()->create(['activo' => true, 'es_administrador' => true]);
        $archivo = app(GestorArchivos::class)->guardar('contenido', 'foto.png', 'image/png', ClaseArchivo::FotoTarea, null, $this->lago->id);

        $this->actingAs($admin)->get('/archivos/'.$archivo->id)->assertOk();

        $admin->update(['activo' => false]);
        $this->actingAs($admin->refresh())->get('/archivos/'.$archivo->id)->assertForbidden();
    });

    it('desactivar o cambiar la contraseña cierra las demás sesiones', function () {
        $usuario = User::factory()->create(['activo' => true, 'remember_token' => 'token-viejo']);
        $sesion = fn (string $id) => DB::table('sessions')->insert(['id' => $id, 'user_id' => $usuario->id, 'payload' => '', 'last_activity' => time()]);
        $sesion('a');
        $sesion('b');

        $usuario->update(['password' => Hash::make('otra-clave-larga-1234')]);
        expect(DB::table('sessions')->where('user_id', $usuario->id)->count())->toBe(0)->and($usuario->refresh()->getRememberToken())->toBe('');

        $sesion('c');
        $usuario->update(['idioma' => 'en']);
        expect(DB::table('sessions')->where('user_id', $usuario->id)->count())->toBe(1);

        $usuario->update(['activo' => false]);
        expect(DB::table('sessions')->where('user_id', $usuario->id)->count())->toBe(0);
    });
});

it('la memoria de permisos no sobrevive a un cambio de roles ni a una propiedad nueva', function () {
    $usuario = ($this->usuario)('recepcion');
    expect(Acceso::propiedades($usuario, Acceso::DATOS))->toBe([$this->lago->id]);

    Acceso::asignarRoles($usuario, [['propiedad_id' => $this->playa->id, 'rol' => 'recepcion']]);
    expect(Acceso::propiedades($usuario, Acceso::DATOS))->toBe([$this->playa->id]);

    $admin = User::factory()->create(['activo' => true, 'es_administrador' => true]);
    expect(Acceso::propiedades($admin, Acceso::DATOS))->toHaveCount(2);
    Propiedad::query()->create(['nombre' => 'Nueva', 'codigo' => 'nueva', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    expect(Acceso::propiedades($admin, Acceso::DATOS))->toHaveCount(3);
});
