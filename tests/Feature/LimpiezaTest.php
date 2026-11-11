<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Archivos\GestorArchivos;
use App\Enums\ClaseArchivo;
use App\Enums\EstadoLimpieza;
use App\Enums\EstadoReserva;
use App\Enums\EstadoTarea;
use App\Enums\FrecuenciaLimpieza;
use App\Enums\ModoPropiedad;
use App\Enums\TipoTarea;
use App\Filament\Pages\Limpieza;
use App\Filament\Resources\Tareas\TareaResource;
use App\Limpieza\GestorLimpieza;
use App\Models\CambioEstadoUnidad;
use App\Models\EstadoUnidad;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\ReglaLimpieza;
use App\Models\Reserva;
use App\Models\Tarea;
use App\Models\Unidad;
use App\Models\User;
use App\Reservas\GestorReservas;
use App\Seguridad\Acceso;
use App\Servicios\Disponibilidad;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN'])->refresh();
    $this->unidad = Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => '101', 'nombre' => '101', 'capacidad' => 2]);
    $this->huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana']);
    $this->hoy = $this->propiedad->hoy();
    $this->gestor = app(GestorLimpieza::class);
    $this->reservar = fn (int $desde, int $hasta, ?Unidad $unidad = null): Reserva => app(GestorReservas::class)->crear($this->propiedad, [
        'unidad_id' => ($unidad ?? $this->unidad)->id, 'titular_id' => $this->huesped->id, 'llegada' => $this->hoy->addDays($desde)->format('Y-m-d'), 'salida' => $this->hoy->addDays($hasta)->format('Y-m-d'),
        'personas' => 1, 'estado' => EstadoReserva::Confirmada->value, 'total' => 1000,
    ]);
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']));
});

it('crea la tarea de salida una sola vez y la marca urgente si alguien llega ese día', function () {
    ($this->reservar)(0, 2);
    expect($this->gestor->generarTareas($this->propiedad))->toBe(1)
        ->and($this->gestor->generarTareas($this->propiedad))->toBe(0);

    $tarea = Tarea::query()->firstOrFail();
    expect($tarea->tipo)->toBe(TipoTarea::LimpiezaSalida)->and($tarea->prioridad)->toBe(0)
        ->and($tarea->fecha->format('Y-m-d'))->toBe($this->hoy->addDays(2)->format('Y-m-d'));

    ($this->reservar)(2, 3);
    $this->gestor->generarTareas($this->propiedad);
    expect(Tarea::query()->count())->toBe(2);
});

it('cancela la tarea si la reserva cambia de fechas y la recupera si vuelve', function () {
    $reserva = ($this->reservar)(0, 2);
    $this->gestor->generarTareas($this->propiedad);
    $original = Tarea::query()->firstOrFail();

    app(GestorReservas::class)->mover($reserva->refresh(), $this->unidad->id, $this->hoy->addDays(3));
    $this->gestor->generarTareas($this->propiedad);
    expect($original->refresh()->estado)->toBe(EstadoTarea::Cancelada)
        ->and(Tarea::query()->where('estado', EstadoTarea::Pendiente->value)->count())->toBe(1);

    app(GestorReservas::class)->mover($reserva->refresh(), $this->unidad->id, $this->hoy);
    $this->gestor->generarTareas($this->propiedad);
    expect($original->refresh()->estado)->toBe(EstadoTarea::Pendiente);
});

it('crea las limpiezas de la estancia según la regla', function () {
    $reserva = ($this->reservar)(0, 4);
    ReglaLimpieza::query()->create(['reserva_id' => $reserva->id, 'frecuencia' => FrecuenciaLimpieza::Diaria]);

    $this->gestor->generarTareas($this->propiedad);
    expect(Tarea::query()->where('tipo', TipoTarea::LimpiezaEstancia->value)->count())->toBe(3);

    // Solo un día de la semana: el de mañana.
    Tarea::query()->delete();
    ReglaLimpieza::query()->where('reserva_id', $reserva->id)->update(['frecuencia' => FrecuenciaLimpieza::Semanal->value, 'dias_semana' => 1 << ($this->hoy->addDay()->dayOfWeekIso - 1)]);
    $this->gestor->generarTareas($this->propiedad);
    expect(Tarea::query()->where('tipo', TipoTarea::LimpiezaEstancia->value)->pluck('fecha')->map->format('Y-m-d')->all())
        ->toBe([$this->hoy->addDay()->format('Y-m-d')]);
});

it('al registrar la salida la unidad queda sucia y terminar la limpieza la deja limpia', function () {
    $reserva = ($this->reservar)(0, 2);
    $gestorReservas = app(GestorReservas::class);
    $gestorReservas->registrarEntrada($reserva->refresh());
    $gestorReservas->registrarSalida($reserva->refresh());

    expect($this->gestor->estado($this->unidad)->limpieza)->toBe(EstadoLimpieza::Sucia);

    $this->gestor->generarTareas($this->propiedad);
    $this->gestor->terminar(Tarea::query()->firstOrFail());
    expect($this->gestor->estado($this->unidad->refresh())->limpieza)->toBe(EstadoLimpieza::Limpia)
        ->and(CambioEstadoUnidad::query()->where('campo', 'limpieza')->count())->toBe(2);

    // Pasar otra vez por las salidas no la vuelve a ensuciar.
    $this->travel(1)->minutes();
    $this->gestor->atenderSalidas($this->propiedad);
    expect($this->gestor->estado($this->unidad)->limpieza)->toBe(EstadoLimpieza::Limpia);
});

it('si el alojamiento inspecciona, la unidad queda por revisar', function () {
    $this->propiedad->update(['ajustes' => ['limpieza' => ['inspeccion' => true]]]);
    $tarea = Tarea::query()->create(['propiedad_id' => $this->propiedad->id, 'unidad_id' => $this->unidad->id, 'tipo' => TipoTarea::LimpiezaProfunda, 'titulo' => 'x', 'fecha' => $this->hoy]);

    $this->gestor->terminar($tarea);
    expect($this->gestor->estado($this->unidad)->limpieza)->toBe(EstadoLimpieza::Inspeccion);
});

it('una unidad fuera de servicio no aparece como libre', function () {
    $fechas = [$this->hoy->format('Y-m-d'), $this->hoy->addDay()->format('Y-m-d')];
    expect(Disponibilidad::libres([$this->propiedad->id], $fechas[0], $fechas[1], 1))->toHaveCount(1);

    $this->gestor->cambiar($this->unidad, fueraDeServicio: true);

    expect(Disponibilidad::libres([$this->propiedad->id], $fechas[0], $fechas[1], 1))->toHaveCount(0);
});

it('la pantalla muestra las tareas y deja trabajar con ellas', function () {
    ($this->reservar)(0, 1);

    $pagina = Livewire::test(Limpieza::class)->set('fecha', $this->hoy->addDay()->format('Y-m-d'))->assertSee('101')->assertSee(__('limpieza.tarea_salida', ['unidad' => '101']));
    $tarea = Tarea::query()->firstOrFail();

    $pagina->call('empezar', $tarea->id);
    expect($tarea->refresh()->estado)->toBe(EstadoTarea::EnCurso)->and($tarea->asignada_a)->toBe(auth()->id());

    $pagina->call('terminar', $tarea->id)->call('marcar', $this->unidad->id, 'sucia');
    expect($tarea->refresh()->estado)->toBe(EstadoTarea::Hecha)
        ->and(EstadoUnidad::query()->firstOrFail()->limpieza)->toBe(EstadoLimpieza::Sucia);

    $pagina->call('alternarServicio', $this->unidad->id);
    expect(EstadoUnidad::query()->firstOrFail()->fuera_de_servicio)->toBeTrue();
});

it('reporta una incidencia con foto, y la foto solo se ve con permiso', function () {
    Storage::fake('local');
    Storage::disk('local')->put('subidas-temporales/foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

    Livewire::test(Limpieza::class)
        ->callAction(\Filament\Actions\Testing\TestAction::make('incidencia')->arguments(['unidad' => $this->unidad->id]), ['titulo' => 'Gotea la regadera', 'urgente' => true, 'fuera_de_servicio' => true])
        ->assertHasNoActionErrors();

    $tarea = Tarea::query()->where('tipo', TipoTarea::Mantenimiento->value)->firstOrFail();
    expect($tarea->prioridad)->toBe(1)->and($this->gestor->estado($this->unidad)->fuera_de_servicio)->toBeTrue();

    $archivo = app(GestorArchivos::class)->guardarSubida('subidas-temporales/foto.png', ClaseArchivo::FotoTarea, $tarea, $this->propiedad->id);
    expect(Storage::disk('local')->exists('subidas-temporales/foto.png'))->toBeFalse()
        ->and($tarea->archivos()->count())->toBe(1);
    $this->get(route('archivos.ver', $archivo))->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('el archivo cifrado no se lee en el disco y un usuario sin permiso no lo ve', function () {
    Storage::fake('local');
    $secreto = 'contenido-privado';
    $archivo = app(GestorArchivos::class)->guardar($secreto, 'ine.html', 'text/html', ClaseArchivo::DocumentoIdentidad, null, $this->propiedad->id, cifrar: true);

    expect(Storage::disk('local')->get($archivo->ruta))->not->toContain($secreto)
        ->and(app(GestorArchivos::class)->contenido($archivo))->toBe($secreto);

    // Un HTML subido se descarga: no se abre en nuestro dominio.
    $this->get(route('archivos.ver', $archivo))->assertOk()->assertHeader('Content-Type', 'application/octet-stream');

    $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $this->actingAs($limpieza);
    $this->get(route('archivos.ver', $archivo))->assertNotFound();
});

it('el rol de limpieza entra y no ve las demás propiedades', function () {
    $otra = Propiedad::query()->create(['nombre' => 'Otra', 'codigo' => 'otra', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $ajena = Unidad::query()->create(['propiedad_id' => $otra->id, 'codigo' => 'A', 'nombre' => 'Ajena']);
    $limpieza = User::factory()->create(['activo' => true, 'idioma' => 'es']);
    Acceso::asignarRoles($limpieza, [['propiedad_id' => $this->propiedad->id, 'rol' => 'limpieza']]);
    $this->actingAs($limpieza);

    expect(Limpieza::canAccess())->toBeTrue()->and(TareaResource::canViewAny())->toBeTrue();
    Livewire::test(Limpieza::class)->assertSee('101')->assertDontSee('Ajena')
        ->call('marcar', $ajena->id, 'sucia')->assertNotFound();
});

it('el enlace de WhatsApp lleva la lista del día', function () {
    ($this->reservar)(0, 1);
    $this->gestor->generarTareas($this->propiedad);

    $enlace = $this->gestor->enlaceAvisoDia($this->propiedad, $this->hoy->addDay());

    expect($enlace)->toStartWith('https://wa.me/?text=')->and(rawurldecode($enlace))->toContain(__('limpieza.tarea_salida', ['unidad' => '101']));
});
