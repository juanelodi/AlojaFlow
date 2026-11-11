<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Actualizacion\ActualizacionAutomatica;
use App\Actualizacion\ErrorActualizacion;
use App\Actualizacion\InstaladorVersion;
use App\Enums\ModoPropiedad;
use App\Filament\Pages\Actualizaciones;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use Filament\Facades\Filament;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

const URL_URGENTE = 'https://dev.alojaflow.com/api/v1/actualizacion-urgente';

afterEach(fn () => Ajustes::olvidar());

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('app'));
    config([
        'alojaflow.version' => '1.0.38',
        'alojaflow.actualizaciones.permitir_en_copia_git' => true,
        'alojaflow.actualizaciones.url_urgente' => URL_URGENTE,
    ]);
    Propiedad::query()->create(['nombre' => 'Posada', 'codigo' => 'posada', 'modo' => ModoPropiedad::Nativa, 'zona_horaria' => 'America/Mexico_City', 'moneda' => 'MXN', 'pais' => 'MX', 'idioma' => 'es']);
    $this->super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);
    // Instalar de verdad se prueba en ActualizacionEnLineaTest; aquí solo se anota qué se habría instalado.
    $this->instalador = new class () extends InstaladorVersion {
        /** @var list<string> */
        public array $instaladas = [];

        public bool $falla = false;

        public function __construct()
        {
        }

        public function instalar(string $version): void
        {
            if ($this->falla) {
                throw new ErrorActualizacion('La firma no coincide');
            }
            $this->instaladas[] = $version;
        }
    };
    $this->app->instance(InstaladorVersion::class, $this->instalador);
    $this->conCron = fn () => Ajustes::guardar('programador.latido', now()->toIso8601String());
    // Sin red de verdad: github.com resuelve a una IP pública.
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return ['140.82.112.3'];
        }
    });
});

function ordenUrgente(?string $version, string $mensaje = 'Corrige una falla de seguridad.'): void
{
    Http::fake([
        URL_URGENTE => Http::response(['version' => $version, 'mensaje' => $mensaje, 'ordenada_en' => now()->toIso8601String()]),
        'api.github.com/*' => Http::response(['tag_name' => 'v1.0.39', 'body' => 'Notas', 'html_url' => 'https://github.com/juanelodi/AlojaFlow/releases/tag/v1.0.39']),
    ]);
}

it('instala sola una actualización urgente más nueva, sin esperar a las 3:00', function () {
    ($this->conCron)();
    ordenUrgente('1.0.39');

    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe(['1.0.39'])
        ->and(app(ActualizacionAutomatica::class)->ultimoIntento())->toMatchArray(['version' => '1.0.39', 'urgente' => true, 'ok' => true])
        ->and(RegistroAuditoria::query()->where('accion', 'actualizacion.automatica')->exists())->toBeTrue();
    Http::assertSent(fn (Request $peticion): bool => $peticion->url() === URL_URGENTE && $peticion->body() === '');
});

it('no instala una versión igual, anterior o que no es una versión', function (?string $version) {
    ($this->conCron)();
    ordenUrgente($version);

    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([]);
})->with(['1.0.38', '1.0.37', '../../1.0.99', '9.9', null]);

it('con las urgentes apagadas no pregunta ni instala', function () {
    ($this->conCron)();
    ordenUrgente('1.0.39');
    app(ActualizacionAutomatica::class)->guardar(false, false, 'America/Mexico_City');

    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([]);
    Http::assertNotSent(fn (Request $peticion): bool => $peticion->url() === URL_URGENTE);
});

it('sin cron no instala nada sola, pero avisa en el panel a quien puede actualizar', function () {
    ordenUrgente('1.0.39', 'Corrige una falla en el motor.');

    $this->artisan('alojaflow:actualizacion-automatica --solo-consultar')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([]);
    $this->actingAs($this->super)->get('/hoy')->assertOk()
        ->assertSee(__('actualizaciones.automaticas.urgente_titulo', ['version' => '1.0.39']))
        ->assertSee('Corrige una falla en el motor.');
    $otro = User::factory()->create(['es_administrador' => false, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($otro)->get('/')->assertDontSee(__('actualizaciones.automaticas.urgente_titulo', ['version' => '1.0.39']));
});

it('las automáticas instalan la versión nueva solo a las 3:00 de la instalación', function () {
    ($this->conCron)();
    ordenUrgente(null);
    app(ActualizacionAutomatica::class)->guardar(true, true, 'America/Mexico_City');

    $this->travelTo(now('America/Mexico_City')->setTime(10, 15));
    ($this->conCron)();
    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();
    expect($this->instalador->instaladas)->toBe([]);

    $this->travelTo(now('America/Mexico_City')->addDay()->setTime(3, 7));
    ($this->conCron)();
    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();
    expect($this->instalador->instaladas)->toBe(['1.0.39']);
});

it('apagadas al empezar: a las 3:00 no instala nada si nadie las prendió', function () {
    ($this->conCron)();
    ordenUrgente(null);
    $this->travelTo(now('America/Mexico_City')->setTime(3, 7));
    ($this->conCron)();

    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect(app(ActualizacionAutomatica::class)->automaticasActivas())->toBeFalse()
        ->and($this->instalador->instaladas)->toBe([]);
});

it('si falla lo anota y no lo reintenta cada hora', function () {
    ($this->conCron)();
    ordenUrgente('1.0.39');
    $this->instalador->falla = true;

    $this->artisan('alojaflow:actualizacion-automatica')->assertFailed();
    $this->instalador->falla = false;
    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([])
        ->and(app(ActualizacionAutomatica::class)->ultimoIntento())->toMatchArray(['version' => '1.0.39', 'ok' => false]);
});

it('en una copia de desarrollo o en la demostración no instala nada', function () {
    ($this->conCron)();
    ordenUrgente('1.0.39');
    config(['alojaflow.actualizaciones.permitir_en_copia_git' => false]);
    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    config(['alojaflow.actualizaciones.permitir_en_copia_git' => true, 'alojaflow.modo_demo' => true]);
    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([]);
});

it('la hora es la de la primera propiedad, y se puede cambiar', function () {
    $automatica = app(ActualizacionAutomatica::class);
    expect($automatica->zona())->toBe('America/Mexico_City');

    $automatica->guardar(true, true, 'Europe/Madrid');
    Ajustes::olvidar();
    expect($automatica->zona())->toBe('Europe/Madrid');

    $automatica->guardar(true, true, 'No/Existe');
    Ajustes::olvidar();
    expect($automatica->zona())->toBe('Europe/Madrid');
});

it('el super administrador las prende desde Actualizaciones; nadie más', function () {
    Livewire::actingAs($this->super)->test(Actualizaciones::class)
        ->set('automaticas', true)->set('urgentes', false)->set('zona', 'America/Cancun')
        ->call('guardarAutomaticas')
        ->assertHasNoErrors();
    Ajustes::olvidar();

    $automatica = app(ActualizacionAutomatica::class);
    expect($automatica->automaticasActivas())->toBeTrue()->and($automatica->urgentesActivas())->toBeFalse()->and($automatica->zona())->toBe('America/Cancun');

    $admin = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => false, 'activo' => true, 'idioma' => 'es']);
    $this->actingAs($admin)->get(Actualizaciones::getUrl())->assertForbidden();
});

it('corre cada hora con cron, y sin cron solo consulta', function () {
    $eventos = collect(app(Schedule::class)->events())->map(fn ($evento): string => (string) $evento->command);

    expect($eventos->contains(fn (string $comando): bool => str_contains($comando, 'alojaflow:actualizacion-automatica')))->toBeTrue()
        ->and(TareasPorVisita::TAREAS)->toHaveKey('alojaflow:actualizacion-automatica --solo-consultar');
});

it('una orden urgente que ya no se pudo volver a consultar no se instala sola', function () {
    ($this->conCron)();
    Ajustes::guardar(ActualizacionAutomatica::AJUSTE_ORDEN, ['version' => '1.0.39', 'mensaje' => 'x', 'consultada_en' => now()->subHours(7)->toIso8601String()]);
    Http::fake([URL_URGENTE => Http::response('caído', 503)]);

    $this->artisan('alojaflow:actualizacion-automatica')->assertSuccessful();

    expect($this->instalador->instaladas)->toBe([]);
});

it('las tareas de cada hora caen en un minuto que también alcanza un cron cada 10 minutos', function () {
    $minutos = collect(app(Schedule::class)->events())
        ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'alojaflow:actualizacion-automatica') || str_contains((string) $evento->command, 'alojaflow:mejora-continua'))
        ->map(fn ($evento): string => explode(' ', $evento->expression)[0]);

    expect($minutos)->toHaveCount(2)
        ->and($minutos->every(fn (string $minuto): bool => ctype_digit($minuto) && (int) $minuto % 10 === 0))->toBeTrue();
});
