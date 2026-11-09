<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\ModoPropiedad;
use App\Enums\TipoContacto;
use App\Filament\Pages\MejoraContinua;
use App\Filament\Pages\ReportarSugerir;
use App\Instalacion\Instalador;
use App\MejoraContinua\ArmadorLatido;
use App\MejoraContinua\ClienteReceptor;
use App\MejoraContinua\EnvioMejora;
use App\MejoraContinua\Permiso;
use App\MejoraContinua\RegistroErrores;
use App\MejoraContinua\ReportesMejora;
use App\MejoraContinua\Saneador;
use App\Models\ContactoHuesped;
use App\Models\Huesped;
use App\Models\Propiedad;
use App\Models\ReporteMejora;
use App\Models\Unidad;
use App\Models\User;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

const RECEPTOR = 'https://dev.alojaflow.com/api/v1/';

afterEach(fn () => Ajustes::olvidar());

beforeEach(function () {
    $this->seed(RolesYPermisosSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('app'));
    config(['alojaflow.mejora_continua.url' => 'https://dev.alojaflow.com/api/v1']);
    $this->propiedad = Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    Unidad::query()->create(['propiedad_id' => $this->propiedad->id, 'codigo' => 'A', 'nombre' => 'Cabaña A', 'capacidad' => 4, 'orden' => 1]);
    $ana = Huesped::query()->create(['propiedad_id' => $this->propiedad->id, 'nombre' => 'Anastasia', 'apellido1' => 'Privada']);
    ContactoHuesped::query()->create(['huesped_id' => $ana->id, 'tipo' => TipoContacto::Correo, 'valor' => 'anastasia@huesped.example', 'valor_normalizado' => 'anastasia@huesped.example', 'orden' => 0]);
    $this->admin = User::factory()->create(['es_administrador' => true, 'activo' => true, 'idioma' => 'es']);
    $this->recepcion = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    $this->peticiones = new ArrayObject();
    $this->fallas = new ArrayObject();
    // El reporte se manda después de responder; aquí, en el momento.
    $this->withoutDefer();
});

/**
 * Un receptor falso que responde como dev.alojaflow.com y anota lo que recibe.
 *
 * @param  array<string, int>  $fallas  ruta => estado HTTP con que debe fallar
 */
function receptorFalso(array $fallas = [], ?string $ultimaRespuesta = null): void
{
    // Las fallas se leen en cada petición: Http::fake acumula los falsos y siempre responde el primero.
    test()->fallas->exchangeArray($fallas);
    Http::fake(function (Request $peticion) use ($ultimaRespuesta) {
        $ruta = substr(strtok($peticion->url(), '?') ?: '', strlen(RECEPTOR));
        test()->peticiones->append(['metodo' => $peticion->method(), 'ruta' => $ruta, 'cuerpo' => $peticion->body(), 'cabeceras' => $peticion->headers()]);
        foreach (test()->fallas->getArrayCopy() as $patron => $estado) {
            if (str_starts_with($ruta, $patron)) {
                return Http::response(['error' => 'falla'], $estado);
            }
        }

        return match (true) {
            $ruta === 'instalaciones' => Http::response(['ficha' => 'afi_prueba'], 201),
            $ruta === 'latidos' => Http::response(['recibido' => true, 'siguiente_en_horas' => 24, 'reportes_abiertos' => 0, 'ultima_respuesta_en' => $ultimaRespuesta]),
            $ruta === 'errores' => Http::response(['recibidos' => 1]),
            $ruta === 'reportes' && $peticion->method() === 'POST' => Http::response(['folio' => 'R-00007', 'estado' => 'nuevo'], 201),
            $ruta === 'reportes' => Http::response(['reportes' => [['clave' => ReporteMejora::query()->value('clave'), 'folio' => 'R-00007', 'estado' => 'planeado', 'respuesta' => 'Va en la 1.0.39', 'respondido_en' => now()->toIso8601String(), 'resuelto_en_version' => null]]]),
            str_ends_with($ruta, '/imagenes') => Http::response(['orden' => 1], 201),
            $ruta === 'instalaciones/yo' => Http::response(null, 204),
            default => Http::response(['error' => 'no_existe'], 404),
        };
    });
}

/**
 * @return list<string>
 */
function rutasPedidas(): array
{
    return array_map(fn (array $p): string => $p['metodo'].' '.$p['ruta'], test()->peticiones->getArrayCopy());
}

it('sin permiso no junta ni manda nada, salvo lo que se decida', function () {
    receptorFalso();

    expect(Permiso::decidido())->toBeFalse();
    RegistroErrores::anotar(new RuntimeException('Falla con ana@example.com'));
    $this->actingAs($this->admin)->get('/hoy')->assertOk();
    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();

    expect(DB::table('mejora_errores')->count())->toBe(0)
        ->and(DB::table('mejora_pantallas')->count())->toBe(0)
        ->and(rutasPedidas())->toBe([]);
});

it('la casilla del instalador decide el permiso', function () {
    app(Instalador::class)->decidirMejoraContinua(true);
    expect(Permiso::concedido())->toBeTrue();

    app(Instalador::class)->decidirMejoraContinua(false);
    Ajustes::olvidar();
    expect(Permiso::decidido())->toBeTrue()->and(Permiso::activa())->toBeFalse();
});

it('con permiso junta los errores saneados, sin los valores de SQL', function () {
    Permiso::guardar(true);

    for ($i = 0; $i < 3; $i++) {
        RegistroErrores::anotar(new QueryException('mysql', 'select * from huespedes where correo = ?', ['anastasia@huesped.example'], new RuntimeException('Duplicate entry')));
    }
    RegistroErrores::anotar(new RuntimeException('No se pudo avisar a anastasia@huesped.example al 7551234567'));

    $filas = DB::table('mejora_errores')->get();
    $todo = (string) json_encode($filas);
    expect($filas)->toHaveCount(2)
        ->and((int) $filas->firstWhere('clase', QueryException::class)?->veces)->toBe(3)
        ->and($todo)->not->toContain('anastasia')->not->toContain('7551234567')
        ->and($todo)->toContain('select * from huespedes where correo = ?');
});

it('cuenta las visitas a cada pantalla solo con permiso', function () {
    $this->actingAs($this->admin)->get('/hoy')->assertOk();
    expect(DB::table('mejora_pantallas')->count())->toBe(0);

    Permiso::guardar(true);
    Ajustes::olvidar();
    $this->actingAs($this->admin)->get('/hoy')->assertOk();
    $this->actingAs($this->admin)->get('/hoy')->assertOk();

    expect((int) DB::table('mejora_pantallas')->where('pantalla', 'pages.hoy')->value('visitas'))->toBe(2);
});

it('se registra, manda los errores y el latido, sin nada de los huéspedes', function () {
    Permiso::guardar(true);
    receptorFalso();
    RegistroErrores::anotar(new RuntimeException('Algo falló'));

    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();

    expect(rutasPedidas())->toBe(['POST instalaciones', 'POST errores', 'POST latidos'])
        ->and(DB::table('mejora_errores')->count())->toBe(0);
    $latido = json_decode((string) collect($this->peticiones)->firstWhere('ruta', 'latidos')['cuerpo'], true);
    $crudo = (string) json_encode($latido);
    expect($latido['version'])->toBe(config('alojaflow.version'))
        ->and($latido['uso']['propiedades']['total'])->toBe(1)
        ->and($latido['uso']['unidades']['total'])->toBe(1)
        ->and($latido['uso']['reservas_30d'])->toHaveKeys(['panel', 'motor', 'sincronizadas', 'canceladas', 'ical'])
        ->and($latido)->not->toHaveKey('identificacion')
        ->and($crudo)->not->toContain('anastasia')->not->toContain('Anastasia')->not->toContain('Posada');
    expect(collect($this->peticiones)->firstWhere('ruta', 'latidos')['cabeceras']['Authorization'][0])->toBe('Bearer afi_prueba')
        ->and(app(ClienteReceptor::class)->registrado())->toBeTrue();

    // El mismo día no vuelve a mandar el latido.
    $this->peticiones->exchangeArray([]);
    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();
    expect(rutasPedidas())->toBe([]);
});

it('manda la identificación solo si se eligió', function () {
    Permiso::guardar(true);
    Permiso::guardarIdentificacion(['nombre' => 'Posada', 'dominio' => 'posada.example', 'correo' => 'admin@posada.example']);

    expect(app(ArmadorLatido::class)->armar()['identificacion'])->toBe(['nombre' => 'Posada', 'dominio' => 'posada.example', 'correo' => 'admin@posada.example']);
});

it('si la ficha deja de valer, se registra de nuevo la próxima vez', function () {
    Permiso::guardar(true);
    receptorFalso(['latidos' => 401]);

    expect(app(EnvioMejora::class)->enviarLatido())->toBeFalse();
    Ajustes::olvidar();
    expect(app(ClienteReceptor::class)->registrado())->toBeFalse();
});

it('cualquiera del equipo manda un reporte con imágenes, que se borran al llegar', function () {
    Storage::fake('local');
    receptorFalso();

    $this->actingAs($this->recepcion)->get(ReportarSugerir::getUrl())->assertOk();
    Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class, ['desde' => 'filament.app.pages.calendario'])
        ->fillForm([
            'tipo' => 'error',
            'titulo' => 'El calendario no carga',
            'descripcion' => 'Se queda en blanco.',
            'pasos' => 'Abrir el calendario',
            'imagenes' => [UploadedFile::fake()->image('captura.png', 40, 30), UploadedFile::fake()->image('otra.png', 40, 30)],
            'adjuntar_diagnostico' => false,
        ])
        ->call('enviar')
        ->assertHasNoFormErrors();

    $reporte = ReporteMejora::query()->firstOrFail();
    expect($reporte->envio)->toBe(ReporteMejora::ENVIO_ENVIADO)
        ->and($reporte->folio)->toBe('R-00007')
        ->and($reporte->user_id)->toBe($this->recepcion->id)
        ->and($reporte->pantalla)->toBe('filament.app.pages.calendario')
        ->and($reporte->imagenes_enviadas)->toBe(2)
        ->and(Storage::disk('local')->allFiles('mejora/reportes'))->toBe([])
        ->and(rutasPedidas())->toBe(['POST instalaciones', 'POST reportes', 'POST reportes/R-00007/imagenes', 'POST reportes/R-00007/imagenes']);
    $cuerpo = json_decode((string) collect($this->peticiones)->firstWhere('ruta', 'reportes')['cuerpo'], true);
    expect($cuerpo)->not->toHaveKey('diagnostico')->and($cuerpo['tipo'])->toBe('error');
});

it('un reporte que no pudo salir se reintenta cada hora, aun sin permiso de datos de uso', function () {
    receptorFalso(['reportes' => 503]);
    Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class)
        ->fillForm(['tipo' => 'sugerencia', 'titulo' => 'Agrupar por piso', 'descripcion' => 'En limpieza.'])
        ->call('enviar')
        ->assertHasNoFormErrors();
    expect(ReporteMejora::query()->firstOrFail()->envio)->toBe(ReporteMejora::ENVIO_ERROR);

    $this->fallas->exchangeArray([]);
    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();

    expect(ReporteMejora::query()->firstOrFail()->envio)->toBe(ReporteMejora::ENVIO_ENVIADO);
});

it('trae la respuesta del equipo cuando el latido avisa', function () {
    Permiso::guardar(true);
    ReporteMejora::query()->create(['user_id' => $this->recepcion->id, 'clave' => '0b9f2c1e-3c0a-4c55-9d2b-111111111111', 'tipo' => 'sugerencia', 'titulo' => 'Idea', 'descripcion' => 'Algo', 'envio' => 'enviado', 'folio' => 'R-00007', 'estado' => 'nuevo']);
    receptorFalso(ultimaRespuesta: now()->toIso8601String());

    expect(app(EnvioMejora::class)->enviarLatido())->toBeTrue();

    $reporte = ReporteMejora::query()->firstOrFail();
    expect($reporte->estado)->toBe('planeado')->and($reporte->respuesta)->toBe('Va en la 1.0.39');
    $this->actingAs($this->recepcion)->get(ReportarSugerir::getUrl())->assertOk()->assertSee('Va en la 1.0.39');
});

it('el administrador decide desde el aviso; el resto del equipo no', function () {
    $pagina = (string) $this->actingAs($this->admin)->get('/hoy')->assertOk()->assertSee(__('mejora.aviso.titulo'))->getContent();
    // Sin formulario en el contenido: en el teléfono lo ocultaría hasta que arranca Alpine (D-067).
    expect((string) strstr($pagina, 'class="fi-main-ctn"'))->not->toContain('<form');
    $this->actingAs($this->recepcion)->post(route('mejora-continua.decidir'), ['decision' => 'si'])->assertForbidden();
    $this->actingAs($this->admin)->postJson(route('mejora-continua.decidir'), ['decision' => 'si'])->assertNoContent();

    $this->actingAs($this->admin)->post(route('mejora-continua.decidir'), ['decision' => 'no'])->assertRedirect();
    Ajustes::olvidar();

    expect(Permiso::decidido())->toBeTrue()->and(Permiso::concedido())->toBeFalse();
    $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee(__('mejora.aviso.titulo'));
});

it('la pantalla de mejora continua es del administrador, y olvida la instalación', function () {
    Permiso::guardar(true);
    receptorFalso();
    app(ClienteReceptor::class)->asegurarRegistro();

    $this->actingAs($this->recepcion)->get(MejoraContinua::getUrl())->assertForbidden();
    $this->actingAs($this->admin)->get(MejoraContinua::getUrl())->assertOk()->assertSee(__('mejora.que_no'));

    Livewire::actingAs($this->admin)->test(MejoraContinua::class)->callAction('olvidar')->assertHasNoActionErrors();
    Ajustes::olvidar();

    expect(rutasPedidas())->toContain('DELETE instalaciones/yo')
        ->and(app(ClienteReceptor::class)->registrado())->toBeFalse()
        ->and(Permiso::concedido())->toBeFalse();
});

it('en la demostración pública no se manda nada ni se puede reportar', function () {
    config(['alojaflow.modo_demo' => true]);
    Permiso::guardar(true);
    receptorFalso();

    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();

    expect(rutasPedidas())->toBe([])
        ->and(ReportarSugerir::canAccess())->toBeFalse();
});

it('quita de un error lo que podría ser personal', function () {
    expect(Saneador::texto('Undefined array key "tarifa_base"'))->toBe('Undefined array key "tarifa_base"')
        ->and(Saneador::texto('Valor "Ana Pérez" en /home/hotel/app/Foo.php'))->toBe('Valor "…" en app/Foo.php')
        ->and(Saneador::ruta(base_path('app/Reservas/GestorReservas.php')))->toBe('app/Reservas/GestorReservas.php');
});

it('un lote de errores que el receptor rechaza no detiene el latido', function () {
    Permiso::guardar(true);
    receptorFalso(['errores' => 422]);
    RegistroErrores::anotar(new RuntimeException('Algo falló'));

    $this->artisan('alojaflow:mejora-continua')->assertSuccessful();

    expect(rutasPedidas())->toContain('POST latidos')
        ->and(DB::table('mejora_errores')->count())->toBe(0);
});

it('una imagen rechazada no tumba el reporte, que ya llegó', function () {
    Storage::fake('local');
    receptorFalso(['reportes/R-00007/imagenes' => 422]);
    Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class)
        ->fillForm(['tipo' => 'error', 'titulo' => 'Falla', 'descripcion' => 'Algo pasa.', 'imagenes' => [UploadedFile::fake()->image('rara.png', 10, 10)]])
        ->call('enviar')
        ->assertHasNoFormErrors();

    expect(ReporteMejora::query()->firstOrFail()->envio)->toBe(ReporteMejora::ENVIO_ENVIADO)
        ->and(Storage::disk('local')->allFiles('mejora/reportes'))->toBe([]);
});

it('trae el estado de los reportes abiertos aunque no haya respuesta nueva', function () {
    Permiso::guardar(true);
    ReporteMejora::query()->create(['user_id' => $this->recepcion->id, 'clave' => '0b9f2c1e-3c0a-4c55-9d2b-222222222222', 'tipo' => 'error', 'titulo' => 'Falla', 'descripcion' => 'Algo', 'envio' => 'enviado', 'folio' => 'R-00007', 'estado' => 'nuevo']);
    Ajustes::guardar('mejora.reportes_sincronizados_en', now()->toIso8601String());
    receptorFalso();

    expect(app(EnvioMejora::class)->enviarLatido())->toBeTrue()
        ->and(ReporteMejora::query()->firstOrFail()->estado)->toBe('planeado');
});

it('no cuenta lo que no es una pantalla del panel', function () {
    Permiso::guardar(true);
    $this->get('/codigo-fuente');

    expect(DB::table('mejora_pantallas')->count())->toBe(0);
});

it('solo acepta imágenes JPG o PNG, revisadas por su contenido', function () {
    Storage::fake('local');
    receptorFalso();

    Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class)
        ->fillForm(['tipo' => 'informe', 'titulo' => 'Informe', 'descripcion' => 'Todo bien.', 'imagenes' => [UploadedFile::fake()->create('animada.gif', 10, 'image/gif')]])
        ->call('enviar')
        ->assertHasErrors();
    expect(ReporteMejora::query()->count())->toBe(0);

    // Un archivo que dice ser PNG y no lo es se borra antes de guardarse.
    Storage::disk('local')->put('mejora/reportes/disfrazada.png', 'GIF89a no es png');
    $reporte = app(ReportesMejora::class)->crear($this->recepcion, ['tipo' => 'informe', 'titulo' => 'Informe', 'descripcion' => 'Todo bien.'], ['mejora/reportes/disfrazada.png', '../.env']);
    expect($reporte->tipo)->toBe('informe')
        ->and($reporte->imagenes)->toBe([])
        ->and(Storage::disk('local')->exists('mejora/reportes/disfrazada.png'))->toBeFalse();
});

it('limita cuántos reportes manda cada persona por hora', function () {
    receptorFalso();
    $prueba = Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class);
    for ($i = 0; $i < 11; $i++) {
        $prueba->fillForm(['tipo' => 'sugerencia', 'titulo' => 'Idea '.$i, 'descripcion' => 'Algo.'])->call('enviar');
    }

    expect(ReporteMejora::query()->count())->toBe(10);
});

it('el apartado de mejora continua lo ve todo el equipo, con Reportar o sugerir', function () {
    $pagina = $this->actingAs($this->recepcion)->get(ReportarSugerir::getUrl())->assertOk();
    $pagina->assertSee(__('mejora.nav'))->assertSee(__('mejora.reportar.nav'))->assertDontSee(__('mejora.nav_ajustes'));

    $this->actingAs($this->admin)->get(MejoraContinua::getUrl())->assertOk()->assertSee(__('mejora.reportar.nav'));
});

it('avisa si alguna imagen se descartó', function () {
    Storage::fake('local');
    receptorFalso();
    // Dice ser PNG (pasa el formulario) pero su contenido no lo es.
    $falsa = UploadedFile::fake()->create('rara.png', 5, 'image/png');

    Livewire::actingAs($this->recepcion)->test(ReportarSugerir::class)
        ->fillForm(['tipo' => 'error', 'titulo' => 'Falla', 'descripcion' => 'Algo.', 'imagenes' => [$falsa]])
        ->call('enviar')
        ->assertNotified(__('mejora.reportar.imagenes_descartadas', ['cuantas' => '1']));
});

it('guarda los errores de JavaScript del panel y los problemas del registro, saneados y solo con permiso', function () {
    $error = ['tipo' => 'TypeError', 'mensaje' => 'Cannot read properties of null (reading "ana@huesped.example")', 'archivo' => 'https://hotel.example/js/alojaflow/alojaflow.js?v=1', 'linea' => 42,
        'pila' => "TypeError: x\n    at ordenar (https://hotel.example/js/alojaflow/alojaflow.js?v=1:42:10)", 'pantalla' => '/reservas/1234/editar'];

    $this->actingAs($this->recepcion)->postJson(route('mejora-continua.error-navegador'), $error)->assertNoContent();
    Illuminate\Support\Facades\Log::warning('No se pudo avisar a ana@huesped.example');
    expect(DB::table('mejora_errores')->count())->toBe(0);

    Permiso::guardar(true);
    Ajustes::olvidar();
    $this->actingAs($this->recepcion)->postJson(route('mejora-continua.error-navegador'), $error)->assertNoContent();
    Illuminate\Support\Facades\Log::warning('No se pudo avisar a ana@huesped.example');

    $filas = DB::table('mejora_errores')->orderBy('id')->get();
    expect($filas)->toHaveCount(2)
        ->and($filas[0]->clase)->toBe('JavaScript\TypeError')
        ->and($filas[0]->archivo)->toBe('alojaflow.js')
        ->and(json_decode((string) $filas[0]->contexto, true))->toMatchArray(['origen' => 'navegador', 'ruta' => '/reservas/{n}/editar'])
        ->and($filas[1]->clase)->toBe('Registro\Warning')
        ->and((string) json_encode($filas))->not->toContain('ana@huesped');
    // Sin sesión no se acepta nada.
    auth()->guard()->logout();
    $this->post(route('mejora-continua.error-navegador'), $error)->assertRedirect();
});
