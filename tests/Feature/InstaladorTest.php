<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Actualizacion\Empaquetador;
use App\Instalacion\Instalador;
use App\Models\Propiedad;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

afterEach(fn () => Ajustes::olvidar());

it('el instalador se puede leer en versiones de PHP más viejas que la que pide la aplicación', function () {
    $resultado = Process::run([PHP_BINARY, '-l', base_path('instalador/instalar.php')]);
    expect($resultado->successful())->toBeTrue();

    // Sin sintaxis moderna: tiene que poder decirle a la persona que su PHP es viejo.
    $codigo = (string) file_get_contents(base_path('instalador/instalar.php'));
    $php = substr($codigo, 0, (int) strpos($codigo, '<!doctype html>'));
    expect($php)->not->toMatch('/\bfn\s*\(/')
        ->and($php)->not->toContain('??')
        ->and($php)->not->toContain('?->')
        ->and($php)->not->toMatch('/function\s+\w+\([^)]*\)\s*:\s*\w+/')
        ->and($php)->not->toContain('strict_types')
        ->and(rtrim($codigo))->toEndWith('__halt_compiler();');
});

it('el empaquetador mete el zip dentro del instalador y cambia sus valores', function () {
    $carpeta = archivoTemporal('x');
    $zip = dirname($carpeta).'/alojaflow-9.9.9.zip';
    $archivo = new ZipArchive();
    $archivo->open($zip, ZipArchive::CREATE);
    $archivo->addFromString('alojaflow-9.9.9/hola.txt', 'hola');
    $archivo->close();
    $salida = dirname($carpeta).'/instalar-alojaflow-9.9.9.php';

    app(Empaquetador::class)->construirInstalador(base_path('instalador/instalar.php'), $zip, '9.9.9', 'CLAVE', '8.3.0', ['zip', 'intl'], $salida);

    $contenido = (string) file_get_contents($salida);
    expect($contenido)->toContain("define('AF_VERSION', '9.9.9')")
        ->and($contenido)->toContain("define('AF_CLAVE_PUBLICA', 'CLAVE')")
        ->and($contenido)->toContain("define('AF_EXTENSIONES', 'zip,intl')")
        ->and($contenido)->not->toContain("'@@VERSION@@'");

    // Lo que hay después de __halt_compiler es el zip, íntegro.
    $marca = '__halt_compiler();';
    $datos = substr($contenido, (int) strrpos($contenido, $marca) + strlen($marca));
    expect($datos)->toBe(file_get_contents($zip));
    expect(Process::run([PHP_BINARY, '-l', $salida])->successful())->toBeTrue();
    File::deleteDirectory(dirname($carpeta));
});

it('el paquete protege la carpeta con el .htaccess de la raíz', function () {
    $htaccess = (string) file_get_contents(base_path('.htaccess'));

    expect($htaccess)->toContain('RewriteRule ^(.*)$ public/$1 [L]')
        ->and($htaccess)->toContain('Require all denied');
});

it('prepara la base y crea al administrador', function () {
    $instalador = app(Instalador::class);

    $instalador->migrar();

    expect(Role::query()->where('name', 'gerente')->exists())->toBeTrue();

    $usuario = $instalador->crearAdministrador('Ana', 'ana@example.com', 'una-contrasena-larga');
    expect($usuario->es_administrador)->toBeTrue()->and($usuario->activo)->toBeTrue();
    expect(fn () => $instalador->crearAdministrador('Ana', 'no-es-correo', 'una-contrasena-larga'))->toThrow(ValidationException::class);
    expect(fn () => $instalador->crearAdministrador('Ana', 'ana@example.com', 'corta'))->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(1);
});

it('crea el demo una sola vez y deja la marca de instalado', function () {
    $this->seed(RolesYPermisosSeeder::class);
    $marca = storage_path(Instalador::MARCA);
    $habia = is_file($marca);
    $instalador = app(Instalador::class);

    $instalador->crearDemo();
    $instalador->crearDemo();
    expect(Propiedad::query()->where('es_demo', true)->count())->toBe(1);

    $instalador->terminar();
    expect(is_file($marca))->toBeTrue();
    expect(json_decode((string) file_get_contents($marca), true)['version'])->toBe(config('alojaflow.version'));
    if (! $habia) {
        File::delete($marca);
    }
});

it('sin cron, las tareas corren por turnos y no se repiten; con cron, no corren', function () {
    expect(TareasPorVisita::cronActivo())->toBeFalse();

    $primera = TareasPorVisita::ejecutarPendientes();
    expect($primera)->toContain('alojaflow:sincronizar')->and($primera)->toContain('alojaflow:limpiar');
    expect(TareasPorVisita::ejecutarPendientes())->toBe([]);

    $this->travel(6)->minutes();
    Ajustes::olvidar();
    $luego = TareasPorVisita::ejecutarPendientes();
    expect($luego)->toContain('alojaflow:sincronizar')->and($luego)->not->toContain('alojaflow:limpiar');

    TareasPorVisita::anotarLatido();
    expect(TareasPorVisita::cronActivo())->toBeTrue();
    $this->travel(31)->minutes();
    expect(TareasPorVisita::cronActivo())->toBeFalse();
    expect(TareasPorVisita::comandoCron())->toContain('artisan schedule:run');
});
