<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Copias\ErrorCopia;
use App\Copias\GestorCopias;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\CopiasSeguridad;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\User;
use App\Seguridad\Acceso;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    $this->carpetaOriginal = app()->storagePath();
    $this->almacen = storage_path('framework/testing/'.uniqid('copias', true));
    File::ensureDirectoryExists($this->almacen);
    app()->useStoragePath($this->almacen);
    $this->copias = app(GestorCopias::class);
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
});

afterEach(function () {
    File::deleteDirectory($this->almacen);
    app()->useStoragePath($this->carpetaOriginal);
    Ajustes::olvidar();
});

function restaurable(): void
{
    // RefreshDatabase abre una transacción que los DROP de la restauración cerrarían.
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
}

it('crea un zip con la base, la ficha y los archivos subidos', function () {
    File::ensureDirectoryExists(storage_path('app/private/archivos/planos'));
    File::put(storage_path('app/private/archivos/planos/uno.txt'), 'hola');

    $copia = $this->copias->crear();

    $lista = $this->copias->listar();
    expect($lista)->toHaveCount(1)
        ->and($lista[0]['nombre'])->toBe($copia['nombre'])
        ->and($lista[0]['valida'])->toBeTrue()
        ->and($lista[0]['misma_clave'])->toBeTrue()
        ->and($lista[0]['archivos'])->toBe(1)
        ->and($lista[0]['version'])->toBe(config('alojaflow.version'));

    $zip = new ZipArchive();
    $zip->open($this->copias->ruta($copia['nombre']));
    expect($zip->locateName('base-datos.sql.gz'))->not->toBeFalse()
        ->and($zip->getFromName('archivos/planos/uno.txt'))->toBe('hola')
        ->and($zip->locateName('.env'))->toBeFalse();
    $zip->close();
});

it('restaura datos con texto difícil, sin dejar lo que no estaba en la copia', function () {
    restaurable();
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'UTC', 'moneda' => 'MXN']);
    $dificil = "línea uno;\nlínea dos; 'comillas' \"dobles\" \\ barra;\n--no es comentario;";
    $huesped = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Ana', 'notas' => $dificil, 'documento_numero' => 'ABC123']);
    File::ensureDirectoryExists(storage_path('app/private/archivos'));
    File::put(storage_path('app/private/archivos/a.txt'), 'original');
    $copia = $this->copias->crear();

    $huesped->update(['nombre' => 'Cambiado']);
    Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Nuevo']);
    File::put(storage_path('app/private/archivos/a.txt'), 'cambiado');
    File::put(storage_path('app/private/archivos/b.txt'), 'de más');

    $this->copias->restaurar($copia['nombre']);

    expect(Huesped::query()->count())->toBe(1);
    $restaurado = Huesped::query()->firstOrFail();
    expect($restaurado->nombre)->toBe('Ana')
        ->and($restaurado->notas)->toBe($dificil)
        ->and($restaurado->documento_numero)->toBe('ABC123')
        ->and(File::get(storage_path('app/private/archivos/a.txt')))->toBe('original')
        ->and(is_file(storage_path('app/private/archivos/b.txt')))->toBeFalse();

    // Se guardó el estado anterior y se puede volver a él.
    $seguridad = collect($this->copias->listar())->firstWhere('origen', 'antes_de_restaurar');
    expect($seguridad)->not->toBeNull();
    $this->copias->restaurar($seguridad['nombre']);
    expect(Huesped::query()->count())->toBe(2);

    $this->artisan('migrate:fresh');
})->group('restaura');

it('no restaura una copia de una versión más nueva ni un zip ajeno', function () {
    $copia = $this->copias->crear();
    $ruta = $this->copias->ruta($copia['nombre']);
    $zip = new ZipArchive();
    $zip->open($ruta);
    $ficha = json_decode((string) $zip->getFromName('copia.json'), true);
    $ficha['version'] = '99.0.0';
    $zip->addFromString('copia.json', (string) json_encode($ficha));
    $zip->close();

    expect(fn () => $this->copias->restaurar($copia['nombre']))->toThrow(ErrorCopia::class, '99.0.0');

    $ajeno = storage_path('app/ajeno.zip');
    File::ensureDirectoryExists(dirname($ajeno));
    $otro = new ZipArchive();
    $otro->open($ajeno, ZipArchive::CREATE);
    $otro->addFromString('hola.txt', 'hola');
    $otro->close();
    expect(fn () => $this->copias->importar($ajeno, 'ajeno.zip'))->toThrow(ErrorCopia::class);
    expect(fn () => $this->copias->ruta('../../.env'))->toThrow(ErrorCopia::class);
});

it('borra las copias de más según el ajuste', function () {
    Ajustes::guardar('copias.conservar', 2);
    $this->copias->crear();
    $this->copias->crear();
    $this->copias->crear();

    expect($this->copias->listar())->toHaveCount(2);
});

it('la copia programada respeta el ajuste de copias automáticas', function () {
    Ajustes::guardar('copias.automaticas', false);
    $this->artisan('alojaflow:copia --programada')->assertSuccessful();
    expect($this->copias->listar())->toHaveCount(0);

    $this->artisan('alojaflow:copia')->assertSuccessful();
    expect($this->copias->listar())->toHaveCount(1);
});

it('un gerente no entra a las copias de seguridad', function () {
    $gerente = User::factory()->create(['es_administrador' => false, 'activo' => true]);
    Acceso::asignarRoles($gerente, [['propiedad_id' => $this->propiedad->id, 'rol' => 'gerente']]);

    $this->actingAs($gerente)->get('/copias-de-seguridad')->assertForbidden();
});

it('el administrador crea, descarga y borra copias', function () {
    $administrador = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es', 'password' => 'una-contrasena-larga']);
    $this->actingAs($administrador);
    $this->get('/copias-de-seguridad')->assertOk()->assertSee(__('copias.vacio'));

    Livewire::test(CopiasSeguridad::class)->callAction('crear')->assertNotified();
    $nombre = $this->copias->listar()[0]['nombre'];

    Livewire::test(CopiasSeguridad::class)
        ->assertSee($nombre)
        ->callAction('descargar', arguments: ['nombre' => $nombre])
        ->assertFileDownloaded($nombre);

    Livewire::test(CopiasSeguridad::class)
        ->callAction('restaurar', data: ['contrasena' => 'equivocada'], arguments: ['nombre' => $nombre])
        ->assertHasActionErrors(['contrasena']);

    Livewire::test(CopiasSeguridad::class)
        ->callAction('eliminar', arguments: ['nombre' => $nombre]);
    expect($this->copias->listar())->toHaveCount(0);
});

it('descargar la configuración pide la contraseña', function () {
    $administrador = User::factory()->create(['es_administrador' => true, 'activo' => true, 'password' => 'una-contrasena-larga']);
    $this->actingAs($administrador);

    Livewire::test(CopiasSeguridad::class)
        ->callAction('descargarEnv', data: ['contrasena' => 'equivocada'])
        ->assertHasActionErrors(['contrasena']);
});
