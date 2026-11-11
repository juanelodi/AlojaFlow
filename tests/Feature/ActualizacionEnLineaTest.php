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
use App\Actualizacion\DescargaPorPedazos;
use App\Actualizacion\ErrorActualizacion;
use App\Actualizacion\Revisor;
use App\Actualizacion\UltimaVersion;
use App\Enums\EstadoActualizacion;
use App\Filament\Pages\Actualizaciones;
use App\Filament\Pages\Resumen;
use App\Models\Actualizacion;
use App\Models\User;
use App\Modulos\Modulos;
use App\Programador\TareasPorVisita;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use GuzzleHttp\Promise\RejectedPromise;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\ConstructorPaquete;

const URL_API = 'https://api.github.com/repos/juanelodi/AlojaFlow/releases/latest';
const URL_ZIP = 'https://github.com/juanelodi/AlojaFlow/releases/download/v1.0.2/alojaflow-1.0.2.zip';

beforeEach(function () {
    Ajustes::olvidar();
    $this->tmp = storage_path('framework/testing/en-linea-'.bin2hex(random_bytes(4)));
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
    // Sin red de verdad: github.com resuelve a una IP pública, y lo interno a una privada.
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return str_contains($host, 'interno') ? ['10.0.0.8'] : ['140.82.112.3'];
        }
    });

    ConstructorPaquete::instalar($this->paquetes->construir('1.0.1', ['app/Viejo.php' => 'viejo'], $this->tmp.'/alojaflow-1.0.1.zip'), '1.0.1', $this->raiz);
    $this->zip = (string) file_get_contents($this->paquetes->construir('1.0.2', [
        'app/Viejo.php' => 'nuevo',
        'CAMBIOS.md' => "# Cambios\n\n## 1.0.2 — 2026-11-07\n\n- Novedad de prueba.\n",
    ], $this->tmp.'/alojaflow-1.0.2.zip'));

    $this->super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->conocerVersion = fn (string $version = '1.0.2') => Ajustes::guardar(UltimaVersion::AJUSTE_CONSULTA, [
        'version' => $version, 'notas' => '**Novedad** de prueba', 'enlace' => 'https://github.com/juanelodi/AlojaFlow/releases/tag/v'.$version,
        'consultada_en' => '2026-11-06T03:50:00+00:00', 'fallo' => false,
    ]);
    // Un servidor de archivos que entiende de rangos y manda a lo sumo $maximo bytes cada vez.
    $this->servir = fn (string $contenido, int $maximo = 8388608) => function (Request $peticion) use ($contenido, $maximo) {
        preg_match('/bytes=(\d+)-(\d+)/', $peticion->header('Range')[0] ?? '', $m);
        $total = strlen($contenido);
        $desde = (int) $m[1];
        if ($desde >= $total) {
            return Http::response('', 416, ['Content-Range' => 'bytes */'.$total]);
        }
        $hasta = min((int) $m[2], $desde + $maximo - 1, $total - 1);

        return Http::response(substr($contenido, $desde, $hasta - $desde + 1), 206, ['Content-Range' => "bytes $desde-$hasta/$total"]);
    };
    $this->pedazo = fn () => $this->actingAs($this->super)->postJson(route('actualizaciones.descargar-pedazo'));
    $this->entrada = fn (string $nombre = 'alojaflow-1.0.2.zip') => $this->datos.'/entrada/'.$nombre;
    $this->parcial = fn () => $this->datos.'/descargas/alojaflow-1.0.2.zip.parcial';
});

afterEach(function () {
    File::deleteDirectory($this->tmp);
});

// --- Avisar ---

it('avisa al super administrador de la versión nueva y de lo que cambia, sin mandar datos', function () {
    Http::fake([URL_API => Http::response([
        'tag_name' => 'v1.0.2', 'body' => "**Novedad** de prueba\n\n<script>alert(1)</script>", 'html_url' => 'https://github.com/juanelodi/AlojaFlow/releases/tag/v1.0.2',
        'draft' => false, 'prerelease' => false,
    ])]);

    expect(Artisan::call('alojaflow:buscar-version'))->toBe(0);

    Http::assertSent(function (Request $peticion): bool {
        // Sin parámetros ni cuerpo, y la versión o el dominio no viajan en ninguna cabecera.
        $cabeceras = json_encode($peticion->headers());

        return $peticion->url() === URL_API && $peticion->method() === 'GET' && $peticion->body() === ''
            && ! str_contains((string) $cabeceras, '1.0.1') && ! str_contains((string) $cabeceras, (string) parse_url((string) config('app.url'), PHP_URL_HOST));
    });
    expect(app(UltimaVersion::class)->disponible()['version'] ?? null)->toBe('1.0.2')
        ->and(Actualizaciones::getNavigationBadge())->toBeNull(); // sin sesión no se anuncia

    $this->actingAs($this->super);
    expect(Actualizaciones::getNavigationBadge())->toBe('1.0.2');
    $this->get(Actualizaciones::getUrl())->assertOk()
        ->assertSee(__('actualizaciones.hay_version', ['version' => '1.0.2']))
        ->assertSee('<strong>Novedad</strong> de prueba', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee(__('actualizaciones.actualizar_ahora'));

    // En el inicio, ya con las primeras preguntas contestadas.
    Modulos::marcarPreguntas('prueba');
    $this->get(Resumen::getUrl())->assertOk()->assertSee(__('actualizaciones.hay_version', ['version' => '1.0.2']));
});

it('no avisa a quien no puede instalar', function () {
    ($this->conocerVersion)();
    $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);

    $this->actingAs($admin)->get(Resumen::getUrl())->assertOk()->assertDontSee(__('actualizaciones.hay_version', ['version' => '1.0.2']));
    expect(Actualizaciones::getNavigationBadge())->toBeNull();
});

it('con la misma versión o una anterior no hay nada que avisar', function (string $version) {
    Http::fake([URL_API => Http::response(['tag_name' => 'v'.$version, 'body' => 'x'])]);
    config(['alojaflow.version' => '1.0.2']);

    Artisan::call('alojaflow:buscar-version');

    expect(app(UltimaVersion::class)->disponible())->toBeNull()
        ->and(app(UltimaVersion::class)->ultimaConsulta()['fallo'])->toBeFalse();
    $this->actingAs($this->super)->get(Actualizaciones::getUrl())->assertOk()->assertDontSee(__('actualizaciones.actualizar_ahora'));
})->with(['1.0.2', '1.0.1']);

it('se deja de avisar al quedar instalada, sin borrar nada', function () {
    ($this->conocerVersion)();
    expect(app(UltimaVersion::class)->disponible())->not->toBeNull();

    config(['alojaflow.version' => '1.0.2']);

    expect(app(UltimaVersion::class)->disponible())->toBeNull();
});

it('apagado no pregunta a nadie', function () {
    Http::fake();
    app(UltimaVersion::class)->cambiarAviso(false);

    expect(Artisan::call('alojaflow:buscar-version'))->toBe(0);

    Http::assertNothingSent();
});

it('el aviso se apaga y se prende desde la pantalla, y buscar a mano sirve aunque esté apagado', function () {
    Http::fake([URL_API => Http::response(['tag_name' => 'v1.0.2', 'body' => 'x'])]);

    $pantalla = Livewire::actingAs($this->super)->test(Actualizaciones::class)
        ->assertSet('avisoActivo', true)
        ->set('avisoActivo', false);
    expect(app(UltimaVersion::class)->avisoActivo())->toBeFalse();

    $pantalla->call('buscarAhora')->assertSee(__('actualizaciones.hay_version', ['version' => '1.0.2']));
    expect(app(UltimaVersion::class)->disponible()['version'] ?? null)->toBe('1.0.2');

    $pantalla->set('avisoActivo', true);
    expect(app(UltimaVersion::class)->avisoActivo())->toBeTrue();
});

it('si GitHub no responde no se rompe nada y se conserva lo último que se supo', function (Closure $respuesta) {
    ($this->conocerVersion)();
    Http::fake([URL_API => $respuesta]);

    expect(Artisan::call('alojaflow:buscar-version'))->toBe(0);

    $ultima = app(UltimaVersion::class);
    expect($ultima->ultimaConsulta()['fallo'])->toBeTrue()
        ->and($ultima->disponible()['version'] ?? null)->toBe('1.0.2');
    $this->actingAs($this->super)->get(Actualizaciones::getUrl())->assertOk()->assertSee(__('actualizaciones.subir_boton'));
})->with([
    'sin conexión' => fn () => fn () => throw new ConnectionException('cURL error 28'),
    'error 500' => fn () => fn () => Http::response('Algo salió mal', 500),
    'límite de la API' => fn () => fn () => Http::response(['message' => 'API rate limit exceeded'], 403),
    'responde otra cosa' => fn () => fn () => Http::response('<html>hola</html>'),
    'etiqueta rara' => fn () => fn () => Http::response(['tag_name' => 'ultima; rm -rf /', 'body' => 'x']),
    'versión de prueba' => fn () => fn () => Http::response(['tag_name' => 'v9.9.9', 'prerelease' => true]),
]);

it('la pantalla dice que GitHub no respondió', function () {
    Http::fake([URL_API => Http::response('', 503)]);

    Livewire::actingAs($this->super)->test(Actualizaciones::class)
        ->call('buscarAhora')
        ->assertSee(trim(explode('(', __('actualizaciones.consulta_fallida'))[0]));
});

it('la tarea diaria está programada, también sin cron, y en la demo no pregunta', function () {
    expect(TareasPorVisita::TAREAS)->toHaveKey('alojaflow:buscar-version');
    Http::fake();
    config(['alojaflow.modo_demo' => true]);

    Artisan::call('alojaflow:buscar-version');

    Http::assertNothingSent();
});

// --- Bajar ---

it('baja por pedazos siguiendo la redirección de GitHub y deja el zip listo para revisar', function () {
    ($this->conocerVersion)();
    $servir = ($this->servir)($this->zip, 700);
    $rangos = [];
    Http::fake(function (Request $peticion) use ($servir, &$rangos) {
        if ($peticion->url() === URL_ZIP) {
            return Http::response('', 302, ['Location' => 'https://release-assets.githubusercontent.com/archivo?firma=abc']);
        }
        expect($peticion->url())->toStartWith('https://release-assets.githubusercontent.com/');
        $rangos[] = $peticion->header('Range')[0];

        return $servir($peticion);
    });

    $vueltas = 0;
    do {
        $respuesta = ($this->pedazo)()->assertOk()->assertJson(['ok' => true]);
        expect($respuesta->json('total'))->toBe(strlen($this->zip))
            ->and($respuesta->json('llevamos'))->toBeGreaterThan(0);
    } while ($respuesta->json('paquete') === null && ++$vueltas < 1000);

    expect($respuesta->json('paquete'))->toBe('alojaflow-1.0.2.zip')
        ->and((string) file_get_contents(($this->entrada)()))->toBe($this->zip)
        ->and(File::files($this->datos.'/descargas'))->toBe([])
        ->and($rangos[0])->toBe('bytes=0-'.(DescargaPorPedazos::TAMANO_PEDAZO - 1))
        ->and($rangos[1])->toBe('bytes=700-'.(700 + DescargaPorPedazos::TAMANO_PEDAZO - 1));

    // Sigue el camino de siempre: revisar y confirmar con la contraseña.
    Livewire::actingAs($this->super)->test(Actualizaciones::class)
        ->call('subidaTerminada', 'alojaflow-1.0.2.zip')
        ->assertSet('revision.valida', true)
        ->assertSet('revision.version', '1.0.2')
        ->callAction('actualizar', data: ['contrasena' => 'password']);
    expect(Actualizacion::query()->latest('id')->first())
        ->estado->toBe(EstadoActualizacion::Preparada)
        ->version_nueva->toBe('1.0.2');
});

it('una descarga cortada sigue desde donde iba, sin repetir ni perder nada', function () {
    ($this->conocerVersion)();
    $servir = ($this->servir)($this->zip, 500);
    $cortar = false;
    $rangos = [];
    Http::fake(function (Request $peticion) use ($servir, &$cortar, &$rangos) {
        $rangos[] = $peticion->header('Range')[0];
        if ($cortar) {
            return new RejectedPromise(new ConnectionException('cURL error 56: conexión reiniciada'));
        }

        return $servir($peticion);
    });

    ($this->pedazo)()->assertOk()->assertJson(['llevamos' => 500, 'paquete' => null]);

    $cortar = true;
    ($this->pedazo)()->assertStatus(422)->assertJson(['ok' => false, 'error' => __('actualizaciones.error.descarga_red')]);
    expect(filesize(($this->parcial)()))->toBe(500)
        ->and(file_exists(($this->entrada)()))->toBeFalse();

    $cortar = false;
    $rangos = [];
    $vueltas = 0;
    do {
        $respuesta = ($this->pedazo)()->assertOk();
    } while ($respuesta->json('paquete') === null && ++$vueltas < 1000);

    expect($rangos[0])->toStartWith('bytes=500-')
        ->and((string) file_get_contents(($this->entrada)()))->toBe($this->zip);
});

it('un pedazo que llega incompleto no se suma', function () {
    ($this->conocerVersion)();
    $total = strlen($this->zip);
    Http::fake([URL_ZIP => Http::response(substr($this->zip, 0, 100), 206, ['Content-Range' => 'bytes 0-499/'.$total])]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_red')]);

    expect(file_exists(($this->parcial)()))->toBeFalse()
        ->and(File::files($this->datos.'/descargas'))->toBe([]);
});

it('si el servidor manda otro rango se empieza de nuevo', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->parcial)()));
    File::put(($this->parcial)(), str_repeat('x', 100));
    Http::fake([URL_ZIP => Http::response('yyyyy', 206, ['Content-Range' => 'bytes 300-304/1000'])]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_rango')]);

    expect(file_exists(($this->parcial)()))->toBeFalse();
});

it('si se perdió la respuesta del último pedazo termina igual', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->parcial)()));
    File::put(($this->parcial)(), $this->zip);
    Http::fake([URL_ZIP => ($this->servir)($this->zip)]);

    ($this->pedazo)()->assertOk()->assertJson(['paquete' => 'alojaflow-1.0.2.zip']);
    // Y pedirlo otra vez no baja nada: ya está.
    Http::fake([URL_ZIP => Http::response('', 500)]);
    ($this->pedazo)()->assertOk()->assertJson(['paquete' => 'alojaflow-1.0.2.zip']);

    expect((string) file_get_contents(($this->entrada)()))->toBe($this->zip);
});

it('funciona con un servidor que no entiende de rangos', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->parcial)()));
    File::put(($this->parcial)(), 'lo de antes no sirve');
    Http::fake([URL_ZIP => Http::response($this->zip)]);

    ($this->pedazo)()->assertOk()->assertJson(['paquete' => 'alojaflow-1.0.2.zip']);

    expect((string) file_get_contents(($this->entrada)()))->toBe($this->zip);
});

it('rechaza un paquete con la firma inválida y no cambia nada', function () {
    ($this->conocerVersion)();
    $ajeno = (string) file_get_contents((new ConstructorPaquete($this->tmp))->construir('1.0.2', ['app/Viejo.php' => 'con otra clave'], $this->tmp.'/ajeno.zip'));
    Http::fake([URL_ZIP => ($this->servir)($ajeno)]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['ok' => false, 'error' => __('actualizaciones.error.firma')]);

    expect(file_exists(($this->entrada)()))->toBeFalse()
        ->and(file_exists(($this->parcial)()))->toBeFalse()
        ->and(app(Actualizador::class)->paquetesDisponibles())->toBe([])
        ->and(File::get($this->raiz.'/app/Viejo.php'))->toBe('viejo')
        ->and(Actualizacion::query()->count())->toBe(0);
});

it('rechaza lo que no es un zip y otra versión firmada, aunque esté bien firmada', function () {
    ($this->conocerVersion)();
    Http::fake([URL_ZIP => Http::response('<html>Página de error del proveedor</html>')]);
    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.firma')]);

    // Una versión vieja, con firma buena, servida como si fuera la nueva: no se baja de versión a nadie.
    $vieja = (string) file_get_contents($this->tmp.'/alojaflow-1.0.1.zip');
    Http::fake([URL_ZIP => ($this->servir)($vieja)]);
    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.firma')]);

    expect(app(Actualizador::class)->paquetesDisponibles())->toBe([]);
});

it('un zip con la firma buena y un archivo alterado lo detiene la revisión de siempre', function () {
    ($this->conocerVersion)();
    ConstructorPaquete::alterar($this->tmp.'/alojaflow-1.0.2.zip', 'alojaflow-1.0.2/app/Viejo.php', 'alterado');
    Http::fake([URL_ZIP => ($this->servir)((string) file_get_contents($this->tmp.'/alojaflow-1.0.2.zip'))]);

    ($this->pedazo)()->assertOk()->assertJson(['paquete' => 'alojaflow-1.0.2.zip']);

    Livewire::actingAs($this->super)->test(Actualizaciones::class)
        ->call('subidaTerminada', 'alojaflow-1.0.2.zip')
        ->assertSet('revision.valida', false)
        ->assertActionHidden('actualizar');
    expect(fn () => app(Actualizador::class)->preparar('alojaflow-1.0.2.zip', false, null))->toThrow(ErrorActualizacion::class);
});

it('solo baja del host configurado', function () {
    ($this->conocerVersion)();
    $pedidas = [];
    Http::fake(function (Request $peticion) use (&$pedidas) {
        $pedidas[] = $peticion->url();

        return Http::response('', 302, ['Location' => 'https://atacante.example/alojaflow.zip']);
    });

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_host', ['host' => 'atacante.example'])]);

    expect($pedidas)->toBe([URL_ZIP]);
});

it('los archivos de GitHub solo valen si el paquete viene de GitHub', function () {
    ($this->conocerVersion)();
    config(['alojaflow.actualizaciones.url_paquete' => 'https://descargas.ejemplo.example/alojaflow-:version.zip']);
    Http::fake(['https://descargas.ejemplo.example/*' => Http::response('', 302, ['Location' => 'https://release-assets.githubusercontent.com/x.zip'])]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_host', ['host' => 'release-assets.githubusercontent.com'])]);
});

it('no sigue una redirección a la red interna ni a http', function (string $destino) {
    ($this->conocerVersion)();
    config(['alojaflow.actualizaciones.url_paquete' => 'https://interno-no.github.com/:version.zip']);
    Http::fake();
    // La redirección ni siquiera se comprueba: el primer destino ya resuelve a una IP privada.
    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_red')]);
    Http::assertNothingSent();

    config(['alojaflow.actualizaciones.url_paquete' => URL_ZIP]);
    Http::fake([URL_ZIP => Http::response('', 302, ['Location' => $destino])]);
    ($this->pedazo)()->assertStatus(422);
    Http::assertNotSent(fn (Request $peticion): bool => $peticion->url() === $destino);
})->with(['https://interno.githubusercontent.com/x.zip', 'http://release-assets.githubusercontent.com/x.zip']);

it('no baja más de lo que cabe en un paquete', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->parcial)()));
    $maximo = (int) config('alojaflow.actualizaciones.tamano_maximo_paquete');
    Http::fake([URL_ZIP => Http::response('hola', 206, ['Content-Range' => 'bytes 0-3/'.($maximo + 1)])]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.demasiado_grande')]);

    expect(file_exists(($this->parcial)()))->toBeFalse();
});

it('un 404 lo explica y no borra lo que ya bajó', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->parcial)()));
    File::put(($this->parcial)(), 'parte');
    Http::fake([URL_ZIP => Http::response('Not Found', 404)]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.descarga_http', ['codigo' => 404])]);

    expect(File::get(($this->parcial)()))->toBe('parte');
});

it('borra lo que quedó de una versión anterior', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists($this->datos.'/descargas');
    File::put($this->datos.'/descargas/alojaflow-1.0.1.zip.parcial', 'viejo');
    Http::fake([URL_ZIP => ($this->servir)($this->zip)]);

    ($this->pedazo)()->assertOk();

    expect(file_exists($this->datos.'/descargas/alojaflow-1.0.1.zip.parcial'))->toBeFalse();
});

it('alojaflow:limpiar quita las descargas abandonadas y deja las recientes', function () {
    File::ensureDirectoryExists($this->datos.'/descargas');
    File::put($this->datos.'/descargas/vieja.parcial', 'x');
    touch($this->datos.'/descargas/vieja.parcial', time() - 8 * 86400);
    File::put($this->datos.'/descargas/reciente.parcial', 'x');

    Artisan::call('alojaflow:limpiar');

    expect(file_exists($this->datos.'/descargas/vieja.parcial'))->toBeFalse()
        ->and(file_exists($this->datos.'/descargas/reciente.parcial'))->toBeTrue();
});

it('sin versión nueva conocida no baja nada, y el cliente no elige cuál', function () {
    Http::fake();

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.sin_version_nueva')]);

    ($this->conocerVersion)('1.0.1');
    $this->actingAs($this->super)->postJson(route('actualizaciones.descargar-pedazo'), ['version' => '9.9.9'])->assertStatus(422);
    Http::assertNothingSent();
});

it('solo el super administrador baja', function () {
    ($this->conocerVersion)();
    Http::fake();
    $admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);

    $this->actingAs($admin)->postJson(route('actualizaciones.descargar-pedazo'))->assertForbidden();

    Http::assertNothingSent();
});

it('sin sesión no baja', function () {
    ($this->conocerVersion)();
    Http::fake();

    $this->postJson(route('actualizaciones.descargar-pedazo'))->assertUnauthorized();

    Http::assertNothingSent();
});

it('en una copia de desarrollo no baja ni ofrece el botón', function () {
    ($this->conocerVersion)();
    Http::fake();
    File::ensureDirectoryExists($this->raiz.'/.git');

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.copia_git')]);

    Http::assertNothingSent();
    $this->actingAs($this->super)->get(Actualizaciones::getUrl())->assertOk()->assertDontSee(__('actualizaciones.actualizar_ahora'));
});

it('con una actualización en marcha no baja', function () {
    ($this->conocerVersion)();
    Http::fake();
    Actualizacion::query()->create([
        'version_anterior' => '1.0.1', 'version_nueva' => '1.0.2', 'estado' => EstadoActualizacion::Aplicando,
        'paquete' => 'alojaflow-1.0.2.zip', 'paquete_sha256' => str_repeat('0', 64),
    ]);

    ($this->pedazo)()->assertStatus(422)->assertJson(['error' => __('actualizaciones.error.en_curso')]);

    Http::assertNothingSent();
});

// --- Subir a mano ---

it('subir el zip a mano sigue disponible, también sin aviso', function () {
    app(UltimaVersion::class)->cambiarAviso(false);

    $this->actingAs($this->super)->get(Actualizaciones::getUrl())->assertOk()
        ->assertSee(__('actualizaciones.subir_boton'))
        ->assertDontSee(__('actualizaciones.actualizar_ahora'));
});

it('con el aviso apagado no se insiste, aunque ya se supiera de una versión', function () {
    Ajustes::guardar(UltimaVersion::AJUSTE_CONSULTA, ['version' => '99.0.0', 'notas' => '', 'enlace' => null, 'consultada_en' => now('UTC')->toIso8601String(), 'fallo' => false]);
    $ultima = app(UltimaVersion::class);
    expect($ultima->paraAvisar()['version'] ?? null)->toBe('99.0.0');

    $ultima->cambiarAviso(false);

    expect($ultima->paraAvisar())->toBeNull()
        // En Actualizaciones se sigue viendo: ahí se entra a buscarla.
        ->and($ultima->disponible()['version'] ?? null)->toBe('99.0.0');
});

it('un zip a medio subir por FTP no se borra, y lo bajado y firmado lo sustituye al terminar', function () {
    ($this->conocerVersion)();
    File::ensureDirectoryExists(dirname(($this->entrada)()));
    File::put(($this->entrada)(), 'a medio subir');

    Http::fake([URL_ZIP => Http::sequence()->push('', 500)->push($this->zip)]);

    ($this->pedazo)()->assertStatus(422);
    expect((string) file_get_contents(($this->entrada)()))->toBe('a medio subir');

    ($this->pedazo)()->assertOk()->assertJson(['paquete' => 'alojaflow-1.0.2.zip']);
    expect((string) file_get_contents(($this->entrada)()))->toBe($this->zip);
});
