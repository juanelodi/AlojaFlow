<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Programador\TareasPendientes;
use Carbon\CarbonImmutable;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\Schedule;

/**
 * Un horario de prueba con callbacks que anotan cuándo corrieron: el de verdad lanzaría copias y sincronizaciones.
 */
beforeEach(function () {
    // Las horas fijas, en la zona del alojamiento; la aplicación sigue en UTC.
    $this->corridas = new ArrayObject();
    $corridas = $this->corridas;
    $this->horario = new Schedule('America/Mexico_City');
    $anotar = fn (string $nombre) => function () use ($corridas, $nombre): void {
        $corridas->append($nombre);
    };
    $this->horario->call($anotar('copia'))->name('copia')->dailyAt('03:10');
    $this->horario->call($anotar('cada-hora'))->name('cada-hora')->hourlyAt(40);
    $this->horario->call($anotar('cada-minuto'))->name('cada-minuto')->everyMinute();
    $this->horario->call($anotar('apagada'))->name('apagada')->everyMinute()->when(fn (): bool => false);
    // El de routes/console.php, para la última prueba.
    Illuminate\Support\Facades\Artisan::all();
    $this->horarioReal = app(Schedule::class);
    $this->app->instance(Schedule::class, $this->horario);
});

/**
 * @return list<string>
 */
function corridasYLimpiar(): array
{
    $lista = test()->corridas->getArrayCopy();
    test()->corridas->exchangeArray([]);

    return $lista;
}

it('arrancando fuera de minuto corre lo de hora fija una sola vez, en la primera ronda después de su hora', function () {
    // La ronda empieza a las 3:11:30: schedule:run se saltaría la copia de las 3:10.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:11:30', 'America/Mexico_City'));
    [$codigo, $respuesta] = comandoJson('alojaflow:tareas-pendientes', []);

    expect($codigo)->toBe(0)
        ->and(corridasYLimpiar())->toBe(['copia', 'cada-minuto'])
        ->and($respuesta['corridas'])->toBe(2)
        ->and($respuesta['fallidas'])->toBe(0)
        ->and($respuesta['tareas'][0])->toMatchArray(['tarea' => 'copia', 'estado' => 'correcta'])
        ->and(CarbonImmutable::parse($respuesta['tareas'][0]['debia'])->equalTo(CarbonImmutable::parse('2026-11-10 03:10:00', 'America/Mexico_City')))->toBeTrue()
        ->and($respuesta['tareas'][0])->toHaveKey('duracion_ms');

    // Diez minutos después: la copia ya corrió.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:21:40', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);
    expect(corridasYLimpiar())->toBe(['cada-minuto']);

    // A las 3:43 corre lo de «a los :40», aunque la ronda empiece tres minutos tarde.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:43:05', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);
    expect(corridasYLimpiar())->toBe(['cada-hora', 'cada-minuto']);

    // Al día siguiente, la copia vuelve a correr una vez.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 03:19:59', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);
    $this->travelTo(CarbonImmutable::parse('2026-11-11 03:29:59', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);
    expect(array_count_values(corridasYLimpiar())['copia'])->toBe(1);
});

it('lo que tocó hace mucho, la primera vez no corre: la copia de anoche no sale a mediodía', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:05:00', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);
    expect(corridasYLimpiar())->toBe(['cada-minuto']);

    // Con una ventana más grande, sí.
    $this->travelTo(CarbonImmutable::parse('2026-11-10 12:06:00', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', ['--ventana' => 600]);
    expect(corridasYLimpiar())->toBe(['copia', 'cada-hora', 'cada-minuto']);
});

it('una ronda que se encima con otra no corre ninguna tarea dos veces', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:12:00', 'America/Mexico_City'));
    // Mientras corre la copia, empieza otra ronda (el cron siguiente, o AFAPP que reintenta).
    $corridas = $this->corridas;
    $this->horario->call(function () use ($corridas): void {
        $corridas->append('lenta');
        app(TareasPendientes::class)->ejecutar();
    })->name('lenta')->dailyAt('03:10');

    app(TareasPendientes::class)->ejecutar();

    expect(array_count_values(corridasYLimpiar()))->toBe(['copia' => 1, 'cada-minuto' => 1, 'lenta' => 1]);
});

it('una tarea que falla no detiene a las demás, y el código de salida lo avisa', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:12:00', 'America/Mexico_City'));
    $this->horario->call(fn () => throw new RuntimeException('se cayó'))->name('rota')->everyMinute();
    $this->horario->exec(escapeshellarg(PHP_BINARY).' -r "exit(3);"')->everyMinute();
    $corridas = $this->corridas;
    $this->horario->call(function () use ($corridas): void {
        $corridas->append('despues');
    })->name('despues')->everyMinute();

    [$codigo, $respuesta] = comandoJson('alojaflow:tareas-pendientes', []);

    $porTarea = collect($respuesta['tareas'])->keyBy('tarea');
    expect($codigo)->toBe(1)
        ->and($respuesta['fallidas'])->toBe(2)
        ->and($porTarea['rota'])->toMatchArray(['estado' => 'fallida', 'error' => 'RuntimeException'])
        ->and($porTarea->first(fn (array $t): bool => str_contains($t['tarea'], 'exit(3)')))->toMatchArray(['estado' => 'fallida', 'error' => 'exit 3'])
        ->and($porTarea['despues']['estado'])->toBe('correcta')
        ->and(corridasYLimpiar())->toContain('despues');

    // La que falló no se repite en la misma ronda de minuto: espera a que le vuelva a tocar.
    [, $respuesta] = comandoJson('alojaflow:tareas-pendientes', []);
    expect($respuesta['corridas'])->toBe(0);
});

it('no repite lo que ya corrió schedule:run', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:10:20', 'America/Mexico_City'));
    $copia = collect($this->horario->events())->first(fn ($evento): bool => $evento->description === 'copia');
    event(new ScheduledTaskStarting($copia));

    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:14:00', 'America/Mexico_City'));
    comandoJson('alojaflow:tareas-pendientes', []);

    expect(corridasYLimpiar())->toBe(['cada-minuto']);
});

it('corre igual en una instancia del servicio alojado, y sin JSON dice qué corrió', function () {
    comoInstancia();
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:11:30', 'America/Mexico_City'));

    $this->artisan('alojaflow:tareas-pendientes')->assertSuccessful()->expectsOutputToContain('copia');
    $this->artisan('alojaflow:tareas-pendientes')->assertSuccessful()->expectsOutputToContain(__('consola.tareas_pendientes.nada'));
    $this->artisan('alojaflow:tareas-pendientes', ['--ventana' => 0])->assertExitCode(2);
});

it('reconoce las tareas del horario de verdad por su comando', function () {
    $nombres = collect($this->horarioReal->events())->map(fn ($evento): string => TareasPendientes::nombre($evento));

    expect($nombres)->toContain('latido', 'alojaflow:copia --programada', 'alojaflow:mejora-continua', 'soporte-vencer');
});

it('antes de las tareas pone la base al día si el código cambió sin que nadie abriera la instalación', function () {
    // Como en una instancia a la que AlojaFlow App cambió de versión: la marca dice otra versión que la de los archivos.
    Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/private'));
    Illuminate\Support\Facades\File::put(storage_path(App\Instalacion\Instalador::MARCA), '{}');
    Illuminate\Support\Facades\File::put(storage_path('app/private/esquema-version.txt'), '1.0.0');
    $this->travelTo(CarbonImmutable::parse('2026-11-10 03:11:30', 'America/Mexico_City'));

    [$codigo, $respuesta] = comandoJson('alojaflow:tareas-pendientes', []);

    expect($codigo)->toBe(0)
        ->and($respuesta['tareas'][0])->toMatchArray(['tarea' => 'esquema', 'estado' => 'correcta'])
        ->and(corridasYLimpiar())->toBe(['copia', 'cada-minuto'])
        ->and(app(App\Actualizacion\EsquemaAlDia::class)->hayQueRevisar())->toBeFalse();
});

it('una tarea que no se puede revisar no detiene a las que siguen, y la zona puede venir como DateTimeZone', function () {
    $corridas = $this->corridas;
    $horario = new Schedule('America/Mexico_City');
    $horario->call(fn () => null)->name('rota')->everyMinute()->when(function (): bool {
        throw new RuntimeException('la base no responde');
    });
    $horario->call(function () use ($corridas): void {
        $corridas->append('con-zona');
    })->name('con-zona')->everyMinute()->timezone(new DateTimeZone('America/Mexico_City'));
    $this->app->instance(Schedule::class, $horario);

    [$codigo, $respuesta] = comandoJson('alojaflow:tareas-pendientes', []);

    expect($codigo)->toBe(1)
        ->and($respuesta['tareas'][0])->toMatchArray(['tarea' => 'rota', 'estado' => 'fallida', 'error' => 'RuntimeException'])
        ->and(corridasYLimpiar())->toBe(['con-zona']);
});
