<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Enums\OrigenEjecucion;
use App\Models\Propiedad;
use App\Models\Reserva;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Sincronizacion\HotelDruid\Sincronizador;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\Http;
use Tests\Support\ConstructorCopia;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return ['93.184.215.14'];
        }
    });

    // Dos propiedades sincronizadas con copias sintéticas, con reservas alrededor de hoy.
    $hoy = now('America/Mexico_City');
    $anio = (int) $hoy->format('Y');
    $this->propiedades = [];
    $copiaActual = null;
    Http::fake(function ($peticion) use (&$copiaActual) {
        return ($peticion->data()['salva_backup'] ?? null) === 'SI'
            ? Http::response($copiaActual->gzip())
            : Http::response('<!-- Backup_created --><button name="crea_backup"></button>');
    });
    foreach (['Lago' => 'Cabaña', 'Playa' => 'Suite'] as $nombre => $unidad) {
        $propiedad = Propiedad::query()->create([
            'nombre' => 'Hotel '.$nombre, 'codigo' => strtolower($nombre), 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN',
        ]);
        $principal = $propiedad->principal()->create(['url_base' => 'https://'.strtolower($nombre).'.example/hd/', 'usuario' => 'u', 'contrasena' => 'c']);
        $copiaActual = ConstructorCopia::nueva()->anios($anio)->unidad($unidad.' 1')->unidad($unidad.' 2')
            ->cliente(1, 'Huésped'.$nombre, 'Ana', ['telefono' => '+52 55 0000 0000'])
            ->reserva($anio, 1, $unidad.' 1', 1, $hoy->format('Y-m-d'), $hoy->copy()->addDays(2)->format('Y-m-d'), ['tariffa_tot' => '2000']);
        app(Sincronizador::class)->sincronizar($principal, OrigenEjecucion::Manual);
        $this->propiedades[$nombre] = $propiedad;
    }
});

function usuarioCon(?string $rol, ?Propiedad $propiedad, bool $administrador = false): User
{
    $usuario = User::factory()->create(['es_administrador' => $administrador, 'activo' => true, 'idioma' => 'es']);
    if ($rol !== null && $propiedad !== null) {
        Acceso::asignarRoles($usuario, [['propiedad_id' => $propiedad->id, 'rol' => $rol]]);
    }

    return $usuario;
}

it('las copias sintéticas se sincronizaron', function () {
    expect(Reserva::query()->count())->toBe(2);
});

it('el administrador ve todas las pantallas', function (string $ruta) {
    $this->actingAs(usuarioCon(null, null, administrador: true))->get($ruta)->assertOk();
})->with(['/', '/hoy', '/calendario', '/disponibilidad', '/indicadores', '/reservas', '/huespedes', '/propiedades', '/usuarios', '/acerca-de', '/profile']);

it('la pantalla Hoy muestra la llegada de la propiedad del usuario y no la de otra', function () {
    $recepcion = usuarioCon('recepcion', $this->propiedades['Lago']);

    $this->actingAs($recepcion)->get('/hoy')
        ->assertOk()
        ->assertSee('Ana HuéspedLago')
        ->assertDontSee('HuéspedPlaya');
});

it('limpieza no ve indicadores, reservas sueltas de otra propiedad ni la configuración', function () {
    $limpieza = usuarioCon('limpieza', $this->propiedades['Lago']);
    $reservaPlaya = Reserva::query()->where('propiedad_id', $this->propiedades['Playa']->id)->firstOrFail();

    $this->actingAs($limpieza)->get('/hoy')->assertOk();
    $this->actingAs($limpieza)->get('/indicadores')->assertForbidden();
    $this->actingAs($limpieza)->get('/propiedades')->assertForbidden();
    $this->actingAs($limpieza)->get('/usuarios')->assertForbidden();
    $this->actingAs($limpieza)->get('/reservas/'.$reservaPlaya->id)->assertNotFound();
});

it('un usuario sin acceso a ninguna propiedad solo ve el inicio', function () {
    $sinAcceso = usuarioCon(null, null);

    $this->actingAs($sinAcceso)->get('/')->assertOk()->assertSee(__('ui.resumen.sin_acceso_texto'));
    $this->actingAs($sinAcceso)->get('/hoy')->assertForbidden();
});

it('un usuario desactivado no entra al panel', function () {
    $usuario = usuarioCon('gerente', $this->propiedades['Lago']);
    $usuario->update(['activo' => false]);

    $this->actingAs($usuario)->get('/')->assertForbidden();
});

it('el código fuente se descarga sin iniciar sesión y sin el .env', function () {
    $respuesta = $this->get('/codigo-fuente')->assertOk();

    $zip = new ZipArchive();
    $zip->open($respuesta->baseResponse->getFile()->getPathname());
    $nombres = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $nombres[] = (string) $zip->getNameIndex($i);
    }

    expect($nombres)->toContain('alojaflow-'.config('alojaflow.version').'/LICENSE')
        ->toContain('alojaflow-'.config('alojaflow.version').'/CONTRIBUTING.md')
        ->and(array_filter($nombres, fn (string $n): bool => str_ends_with($n, '/.env') || str_contains($n, '/storage/') || (str_contains($n, '/vendor/') && ! str_contains($n, '/lang/vendor/'))))->toBe([]);
});

it('la interfaz sale en el idioma del usuario', function (string $idioma, string $texto) {
    $usuario = usuarioCon(null, null, administrador: true);
    $usuario->update(['idioma' => $idioma]);

    $this->actingAs($usuario)->get('/hoy')->assertOk()->assertSee($texto);
})->with([['en', 'Arriving'], ['fr', 'Arrivées'], ['it', 'Arrivi'], ['pt', 'Chegam'], ['de', 'Anreisen']]);

it('el administrador ve el estado del sistema y la auditoría; nadie más', function () {
    $this->actingAs(usuarioCon(null, null, administrador: true));
    $this->get('/estado-del-sistema')->assertOk()->assertSee('artisan schedule:run');
    $this->get('/auditoria')->assertOk();
});

it('un gerente no ve el estado del sistema ni la auditoría', function () {
    $this->actingAs(usuarioCon('gerente', $this->propiedades['Lago']));
    $this->get('/estado-del-sistema')->assertForbidden();
    $this->get('/auditoria')->assertForbidden();
});
