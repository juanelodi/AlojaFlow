<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Actualizacion\Actualizador;
use App\Actualizacion\Aplicador;
use App\Actualizacion\CopiaBaseDatos;
use App\Actualizacion\ErrorActualizacion;
use App\Actualizacion\Revisor;
use App\Actualizacion\SubidaPorPartes;
use App\Enums\EstadoActualizacion;
use App\Filament\Pages\Actualizaciones;
use App\Models\Actualizacion;
use App\Models\User;
use App\Providers\Filament\PanelAplicacionProvider;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\ConstructorPaquete;

beforeEach(function () {
    $this->tmp = storage_path('framework/testing/actualizacion-'.bin2hex(random_bytes(4)));
    $this->raiz = $this->tmp.'/instalacion';
    $this->datos = $this->tmp.'/datos';
    $this->paquetes = new ConstructorPaquete($this->tmp);
    config([
        'alojaflow.version' => '1.0.1',
        'alojaflow.actualizaciones.clave_publica' => $this->paquetes->claves['publica'],
    ]);
    $this->app->bind(Actualizador::class, fn ($app) => new Actualizador(
        $app->make(Revisor::class),
        $app->make(Aplicador::class),
        $app->make(CopiaBaseDatos::class),
        $this->raiz,
        $this->datos,
    ));

    // La 1.0.1 instalada desde su zip, con lo propio de la instalación: .env, datos y cachés.
    $this->version101 = [
        'app/Viejo.php' => 'viejo', 'app/Igual.php' => 'igual', 'app/Obsoleto.php' => 'obsoleto',
        'config/app.php' => 'config 1', 'storage/app/.gitignore' => "*\n",
    ];
    ConstructorPaquete::instalar($this->paquetes->construir('1.0.1', $this->version101, $this->tmp.'/alojaflow-1.0.1.zip'), '1.0.1', $this->raiz);
    File::put($this->raiz.'/.env', 'APP_KEY=secreto');
    File::ensureDirectoryExists($this->raiz.'/storage/app/private');
    File::put($this->raiz.'/storage/app/private/dato.txt', 'dato');
    File::ensureDirectoryExists($this->raiz.'/bootstrap/cache');
    File::put($this->raiz.'/bootstrap/cache/packages.php', '<?php return [];');

    // La 1.0.2 cambia, añade y quita archivos; trae también rutas protegidas, que no se escriben.
    $this->version102 = [
        'app/Viejo.php' => 'nuevo', 'app/Igual.php' => 'igual', 'app/Nuevo.php' => 'archivo nuevo',
        'config/app.php' => 'config 2', 'storage/app/.gitignore' => "cambiado\n", '.env' => 'APP_KEY=del-paquete',
        'CAMBIOS.md' => "# Cambios\n\n## 1.0.2 — 2026-11-03\n\n- Novedad de prueba.\n\n## 1.0.1\n\n- Lo de antes.\n",
    ];
    $this->subir = fn (string $version, array $archivos): string => $this->paquetes->construir($version, $archivos, $this->datos.'/entrada/alojaflow-'.$version.'.zip');

    // Los comandos de artisan (down, up, migrate…) se anotan sin ejecutarse.
    $this->comandos = [];
    $this->fallaMigrar = false;
    $this->fingirArtisan = function (): void {
        Artisan::shouldReceive('call')->andReturnUsing(function (string $comando): int {
            $this->comandos[] = $comando;

            return $comando === 'migrate' && $this->fallaMigrar ? 1 : 0;
        });
        Artisan::shouldReceive('output')->andReturn('SQLSTATE[42S01]: fallo de prueba');
    };
});

afterEach(function () {
    File::deleteDirectory($this->tmp);
});

it('actualiza de extremo a extremo sin tocar la configuración ni los datos', function () {
    ($this->fingirArtisan)();
    ($this->subir)('1.0.2', $this->version102);
    $actualizador = app(Actualizador::class);

    $revision = $actualizador->revisar('alojaflow-1.0.2.zip', false);
    expect($revision->errores)->toBe([])
        ->and($revision->version)->toBe('1.0.2')
        ->and($revision->nuevos)->toEqualCanonicalizing(['app/Nuevo.php', 'CAMBIOS.md'])
        ->and($revision->cambiados)->toEqualCanonicalizing(['app/Viejo.php', 'config/app.php'])
        ->and($revision->borrar)->toBe(['app/Obsoleto.php'])
        ->and($revision->iguales)->toBe(1)
        ->and($revision->notas)->toContain('Novedad de prueba')->not->toContain('Lo de antes');

    $actualizacion = $actualizador->preparar('alojaflow-1.0.2.zip', false, null);
    $actualizador->aplicar($actualizacion);

    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::ArchivosAplicados)
        ->and(File::get($this->raiz.'/app/Viejo.php'))->toBe('nuevo')
        ->and(File::get($this->raiz.'/app/Nuevo.php'))->toBe('archivo nuevo')
        ->and(File::get($this->raiz.'/config/app.php'))->toBe('config 2')
        ->and(File::exists($this->raiz.'/app/Obsoleto.php'))->toBeFalse()
        ->and(File::get($this->raiz.'/.env'))->toBe('APP_KEY=secreto')
        ->and(File::get($this->raiz.'/storage/app/.gitignore'))->toBe("*\n")
        ->and(File::get($this->raiz.'/storage/app/private/dato.txt'))->toBe('dato')
        ->and(File::exists($this->raiz.'/bootstrap/cache/packages.php'))->toBeFalse()
        ->and($this->comandos)->toContain('down')->not->toContain('up');
    $copia = (string) gzdecode((string) File::get($actualizador->carpetaDe($actualizacion).'/'.Actualizador::COPIA_BASE_DATOS));
    expect($copia)->toContain('CREATE TABLE `actualizaciones`');

    $actualizador->finalizar($actualizacion);

    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Completada)
        ->and($this->comandos)->toContain('package:discover', 'migrate', 'up')
        ->and(array_keys($actualizador->manifiestoInstalado() ?? []))->toContain('app/Nuevo.php')->not->toContain('app/Obsoleto.php')
        ->and($actualizador->paquetesDisponibles())->toBe([]);
});

it('si las migraciones fallan vuelve a poner los archivos anteriores', function () {
    ($this->fingirArtisan)();
    ($this->subir)('1.0.2', $this->version102);
    $actualizador = app(Actualizador::class);
    $actualizacion = $actualizador->preparar('alojaflow-1.0.2.zip', false, null);
    $actualizador->aplicar($actualizacion);
    $this->fallaMigrar = true;

    expect(fn () => $actualizador->finalizar($actualizacion))->toThrow(ErrorActualizacion::class);
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Revertida)
        ->and($actualizacion->detalle)->toContain('fallo de prueba')
        ->and(File::get($this->raiz.'/app/Viejo.php'))->toBe('viejo')
        ->and(File::get($this->raiz.'/config/app.php'))->toBe('config 1')
        ->and(File::get($this->raiz.'/app/Obsoleto.php'))->toBe('obsoleto')
        ->and(File::exists($this->raiz.'/app/Nuevo.php'))->toBeFalse()
        ->and($this->comandos[array_key_last($this->comandos)])->toBe('up');
});

it('empaqueta e instala archivos con rutas de más de 260 caracteres', function () {
    ($this->fingirArtisan)();
    // Con la carpeta de trabajo delante pasa de 260, el límite de addFile() en Windows.
    $larga = 'vendor/paquete/'.str_repeat('subcarpeta-larga/', 11).'archivo-con-nombre-largo.php';
    ($this->subir)('1.0.2', $this->version102 + [$larga => 'contenido largo']);
    $actualizador = app(Actualizador::class);
    expect($actualizador->revisar('alojaflow-1.0.2.zip', false)->errores)->toBe([]);

    $actualizacion = $actualizador->preparar('alojaflow-1.0.2.zip', false, null);
    $actualizador->aplicar($actualizacion);
    $actualizador->finalizar($actualizacion);

    expect(file_get_contents($this->raiz.'/'.$larga))->toBe('contenido largo');
});

it('no instala un paquete alterado, con archivos de más o con otra firma', function () {
    $actualizador = app(Actualizador::class);
    $errores = fn (string $paquete): string => implode(' ', $actualizador->revisar($paquete, false)->errores);

    ConstructorPaquete::alterar(($this->subir)('1.0.2', $this->version102), 'alojaflow-1.0.2/app/Viejo.php', 'malicioso');
    expect($errores('alojaflow-1.0.2.zip'))->toContain(__('actualizaciones.error.archivo_alterado', ['ruta' => 'app/Viejo.php']));

    ConstructorPaquete::alterar(($this->subir)('1.0.2', $this->version102), 'alojaflow-1.0.2/public/puerta.php', '<?php // de más');
    expect($errores('alojaflow-1.0.2.zip'))->toContain(__('actualizaciones.error.archivo_de_mas', ['ruta' => 'alojaflow-1.0.2/public/puerta.php']));

    (new ConstructorPaquete($this->tmp))->construir('1.0.2', $this->version102, $this->datos.'/entrada/alojaflow-1.0.2.zip');
    expect($errores('alojaflow-1.0.2.zip'))->toBe(__('actualizaciones.error.firma'));

    File::put($this->datos.'/entrada/cualquiera.zip', 'no es un zip');
    expect($errores('cualquiera.zip'))->toBe(__('actualizaciones.error.no_es_zip'))
        ->and(File::get($this->raiz.'/app/Viejo.php'))->toBe('viejo');
});

it('no instala versiones anteriores ni la misma sin «reinstalar»', function () {
    $actualizador = app(Actualizador::class);

    ($this->subir)('1.0.0', $this->version101);
    expect($actualizador->revisar('alojaflow-1.0.0.zip', false)->errores)->toBe([__('actualizaciones.error.anterior', ['version' => '1.0.0', 'actual' => '1.0.1'])]);

    ($this->subir)('1.0.1', $this->version101);
    expect($actualizador->revisar('alojaflow-1.0.1.zip', false)->errores)->toBe([__('actualizaciones.error.misma', ['version' => '1.0.1'])])
        ->and($actualizador->revisar('alojaflow-1.0.1.zip', true)->valida())->toBeTrue();
});

it('en una copia de git no se actualiza desde el panel', function () {
    ($this->subir)('1.0.2', $this->version102);
    File::ensureDirectoryExists($this->raiz.'/.git');
    $actualizador = app(Actualizador::class);

    expect($actualizador->revisar('alojaflow-1.0.2.zip', false)->errores)->toBe([__('actualizaciones.error.copia_git')]);

    config(['alojaflow.actualizaciones.permitir_en_copia_git' => true]);
    expect($actualizador->revisar('alojaflow-1.0.2.zip', false)->valida())->toBeTrue();
});

it('deshace la actualización que se cortó a medias, y solo pasado un rato', function () {
    ($this->subir)('1.0.2', $this->version102);
    $actualizador = app(Actualizador::class);
    $actualizacion = $actualizador->preparar('alojaflow-1.0.2.zip', false, null);

    // El proceso murió intercambiando: un archivo sustituido, otro creado y el último anotado sin mover.
    $carpeta = $actualizador->carpetaDe($actualizacion);
    File::ensureDirectoryExists($carpeta.'/anterior/app');
    File::move($this->raiz.'/app/Viejo.php', $carpeta.'/anterior/app/Viejo.php');
    File::put($this->raiz.'/app/Viejo.php', 'nuevo');
    File::put($this->raiz.'/app/Nuevo.php', 'archivo nuevo');
    File::put($carpeta.'/diario.log', "sustituye\tapp/Viejo.php\ncrea\tapp/Nuevo.php\nborra\tapp/Obsoleto.php\n");
    $actualizacion->forceFill(['estado' => EstadoActualizacion::Aplicando])->save();

    $this->artisan('alojaflow:finalizar-actualizacion', ['--pendientes' => true])->assertSuccessful();
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Aplicando);

    $this->travel(31)->minutes();
    $this->artisan('alojaflow:finalizar-actualizacion', ['--pendientes' => true])->assertSuccessful();

    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Fallida)
        ->and($actualizacion->detalle)->toBe(__('actualizaciones.error.interrumpida'))
        ->and(File::get($this->raiz.'/app/Viejo.php'))->toBe('viejo')
        ->and(File::exists($this->raiz.'/app/Nuevo.php'))->toBeFalse()
        ->and(File::get($this->raiz.'/app/Obsoleto.php'))->toBe('obsoleto');
});

it('el programador termina la actualización que se quedó a mitad (se cerró el navegador)', function () {
    $consola = app(Kernel::class);
    ($this->fingirArtisan)();
    ($this->subir)('1.0.2', $this->version102);
    $actualizador = app(Actualizador::class);
    $actualizacion = $actualizador->preparar('alojaflow-1.0.2.zip', false, null);
    $actualizador->aplicar($actualizacion);
    Artisan::swap($consola); // la segunda fase con los comandos de verdad (up, migrate…)

    $this->artisan('alojaflow:finalizar-actualizacion', ['--pendientes' => true])->assertSuccessful();
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::ArchivosAplicados);

    $this->travel(3)->minutes();
    $this->artisan('alojaflow:finalizar-actualizacion', ['--pendientes' => true])->assertSuccessful();
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Completada)
        ->and(File::get($this->raiz.'/app/Nuevo.php'))->toBe('archivo nuevo');
});

it('en mantenimiento, el administrador ve en todas las pantallas el aviso para terminarla', function () {
    config(['app.maintenance.driver' => 'cache', 'app.maintenance.store' => 'array']);
    Actualizacion::query()->create([
        'version_anterior' => '1.0.1', 'version_nueva' => '1.0.2', 'estado' => EstadoActualizacion::ArchivosAplicados,
        'paquete' => 'alojaflow-1.0.2.zip', 'paquete_sha256' => str_repeat('0', 64),
    ]);
    $this->actingAs(User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']));
    expect((string) PanelAplicacionProvider::avisoActualizacionSinTerminar())->toBe('');

    app()->maintenanceMode()->activate([]);
    expect((string) PanelAplicacionProvider::avisoActualizacionSinTerminar())->toContain(__('actualizaciones.finalizar_ahora'), '/finalizar?');

    $this->actingAs(User::factory()->create(['activo' => true]));
    expect((string) PanelAplicacionProvider::avisoActualizacionSinTerminar())->toBe('');
});

it('el administrador actualiza desde el panel en dos peticiones', function () {
    ($this->fingirArtisan)();
    ($this->subir)('1.0.2', $this->version102);
    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $actualizacion = app(Actualizador::class)->preparar('alojaflow-1.0.2.zip', false, $admin);
    $aplicar = URL::temporarySignedRoute('actualizaciones.aplicar', now()->addMinutes(30), ['actualizacion' => $actualizacion->id]);

    $this->get($aplicar)->assertRedirect(route('filament.app.auth.login'));
    $this->actingAs(User::factory()->create(['activo' => true]))->get($aplicar)->assertForbidden();
    $this->actingAs($admin)->get(route('actualizaciones.aplicar', $actualizacion))->assertForbidden();
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Preparada);

    $respuesta = $this->actingAs($admin)->get($aplicar);
    $respuesta->assertRedirectContains('/actualizaciones/'.$actualizacion->id.'/finalizar');
    // La cookie para saltar el mantenimiento va sin cifrar: la lee maintenance.php antes de arrancar.
    $cookie = collect($respuesta->headers->getCookies())->first(fn ($c) => $c->getName() === 'laravel_maintenance');
    expect(json_decode((string) base64_decode((string) $cookie?->getValue()), true))->toHaveKeys(['expires_at', 'mac']);

    $this->actingAs($admin)->get((string) $respuesta->headers->get('Location'))
        ->assertRedirect(Actualizaciones::getUrl())
        ->assertSessionHas('actualizacion_ok', '1.0.2');
    expect($actualizacion->refresh()->estado)->toBe(EstadoActualizacion::Completada);

    // Recargar la primera fase no la repite.
    $this->actingAs($admin)->get($aplicar)->assertRedirect(Actualizaciones::getUrl())->assertSessionHas('actualizacion_ok', '1.0.2');
});

it('solo el administrador entra en Actualizaciones, revisa y elimina paquetes', function () {
    ($this->subir)('1.0.2', $this->version102);
    $this->actingAs(User::factory()->create(['activo' => true]))->get(Actualizaciones::getUrl())->assertForbidden();

    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($admin)->get(Actualizaciones::getUrl())->assertOk()->assertSee('alojaflow-1.0.2.zip');

    Livewire::actingAs($admin)->test(Actualizaciones::class)
        ->assertSet('paquete', 'alojaflow-1.0.2.zip')
        ->call('revisar')
        ->assertSet('revision.valida', true)
        ->assertSet('revision.version', '1.0.2')
        ->callAction('eliminarPaquete')
        ->assertSet('paquete', null);
    expect(app(Actualizador::class)->paquetesDisponibles())->toBe([]);
});

it('el zip subido desde el panel queda en la carpeta de entrada con un nombre seguro', function () {
    Storage::fake('local');
    $zip = new ZipArchive();
    $zip->open($this->tmp.'/subido.zip', ZipArchive::CREATE);
    $zip->addFromString('alojaflow-1.0.9/LEEME.txt', 'contenido');
    $zip->close();
    $contenido = (string) file_get_contents($this->tmp.'/subido.zip');
    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);

    $total = (int) ceil(strlen($contenido) / SubidaPorPartes::TAMANO_PARTE);
    $subida = bin2hex(random_bytes(16));

    $this->actingAs($admin);
    $respuesta = $this->post(route('actualizaciones.subir-parte'), [
        'subida' => $subida, 'nombre' => 'alojaflow 1.0.9 (copia).zip', 'indice' => 0, 'total' => $total,
        'parte' => UploadedFile::fake()->createWithContent('parte.bin', $contenido),
    ]);
    $respuesta->assertOk()->assertJson(['ok' => true, 'paquete' => 'alojaflow-1.0.9-copia.zip']);

    expect(file_get_contents($this->datos.'/entrada/alojaflow-1.0.9-copia.zip'))->toBe($contenido)
        ->and(app(Actualizador::class)->paquetesDisponibles())->toBe(['alojaflow-1.0.9-copia.zip']);

    // Terminar de subir revisa sin otro clic.
    Livewire::actingAs($admin)->test(Actualizaciones::class)
        ->call('subidaTerminada', 'alojaflow-1.0.9-copia.zip')
        ->assertSet('paquete', 'alojaflow-1.0.9-copia.zip')
        ->assertNotSet('revision', null);
});

it('arma el zip por partes, en orden, y volver a mandar una parte no la duplica', function () {
    $tamano = SubidaPorPartes::TAMANO_PARTE;
    $contenido = str_repeat('a', $tamano).str_repeat('b', $tamano).'final';
    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($admin);
    $subida = bin2hex(random_bytes(16));
    $mandar = fn (int $indice, string $datos) => $this->post(route('actualizaciones.subir-parte'), [
        'subida' => $subida, 'nombre' => 'alojaflow-1.0.9.zip', 'indice' => $indice, 'total' => 3,
        'parte' => UploadedFile::fake()->createWithContent('parte.bin', $datos),
    ]);

    // La segunda sin la primera no entra.
    $mandar(1, str_repeat('b', $tamano))->assertStatus(422)->assertJson(['ok' => false]);
    $mandar(0, str_repeat('a', $tamano))->assertOk()->assertJson(['ok' => true, 'paquete' => null]);
    // Se perdió la respuesta y el navegador la repite: no se duplica.
    $mandar(0, str_repeat('a', $tamano))->assertOk()->assertJson(['ok' => true, 'paquete' => null]);
    $mandar(1, str_repeat('b', $tamano))->assertOk();
    $mandar(2, 'final')->assertOk()->assertJson(['ok' => true, 'paquete' => 'alojaflow-1.0.9.zip']);
    // Se perdió la respuesta de la última: el zip ya está armado.
    $mandar(2, 'final')->assertOk()->assertJson(['ok' => true, 'paquete' => 'alojaflow-1.0.9.zip']);

    expect(file_get_contents($this->datos.'/entrada/alojaflow-1.0.9.zip'))->toBe($contenido);
});

it('rechaza partes más grandes de lo acordado y subidas con identificador raro', function () {
    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($admin);
    $grande = UploadedFile::fake()->createWithContent('parte.bin', str_repeat('x', SubidaPorPartes::TAMANO_PARTE + 1));
    $this->post(route('actualizaciones.subir-parte'), ['subida' => bin2hex(random_bytes(16)), 'nombre' => 'a.zip', 'indice' => 0, 'total' => 1, 'parte' => $grande])
        ->assertStatus(422);
    $this->post(route('actualizaciones.subir-parte'), ['subida' => '../../x', 'nombre' => 'a.zip', 'indice' => 0, 'total' => 1, 'parte' => UploadedFile::fake()->createWithContent('parte.bin', 'x')])
        ->assertStatus(422);
});

it('solo el super administrador sube partes y abre la pantalla', function () {
    $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($admin);
    $this->post(route('actualizaciones.subir-parte'), ['subida' => bin2hex(random_bytes(16)), 'nombre' => 'a.zip', 'indice' => 0, 'total' => 1, 'parte' => UploadedFile::fake()->createWithContent('parte.bin', 'x')])
        ->assertForbidden();
    $this->get(Actualizaciones::getUrl())->assertForbidden();
});

it('alojaflow:limpiar solo guarda las copias de las últimas actualizaciones', function () {
    $actualizador = app(Actualizador::class);
    File::ensureDirectoryExists($actualizador->carpetaEntrada());
    $carpetas = [];
    foreach (range(1, 5) as $i) {
        $actualizacion = Actualizacion::query()->create([
            'version_anterior' => '1.0.1', 'version_nueva' => '1.0.2', 'estado' => EstadoActualizacion::Completada,
            'paquete' => 'alojaflow-1.0.2.zip', 'paquete_sha256' => str_repeat('0', 64),
        ]);
        File::ensureDirectoryExists($actualizador->carpetaDe($actualizacion));
        $carpetas[] = $actualizador->carpetaDe($actualizacion);
    }

    $this->artisan('alojaflow:limpiar')->assertSuccessful();

    expect(array_map('is_dir', $carpetas))->toBe([false, false, true, true, true])
        ->and(is_dir($actualizador->carpetaEntrada()))->toBeTrue();
});
