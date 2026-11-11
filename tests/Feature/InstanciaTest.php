<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Instalacion\Instalador;
use App\Instalacion\Instancia;
use App\Models\User;
use App\Programador\TareasPorVisita;
use App\Support\Ajustes;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Spatie\Permission\Models\Role;

afterEach(fn () => Ajustes::olvidar());

/**
 * Una carpeta de instancia como la que prepara el agente de AlojaFlow App, con la base de las pruebas: el proceso
 * hijo solo la lee.
 *
 * @return array{0: string, 1: string} la carpeta y su APP_KEY
 */
function carpetaDeInstancia(): array
{
    $carpeta = str_replace('\\', '/', dirname(archivoTemporal('x'))).'/instancia';
    File::ensureDirectoryExists($carpeta.'/storage/framework/cache/data');
    File::ensureDirectoryExists($carpeta.'/storage/framework/views');
    File::ensureDirectoryExists($carpeta.'/storage/logs');
    File::ensureDirectoryExists($carpeta.'/cache');
    $llave = 'base64:'.base64_encode(random_bytes(32));
    $base = (array) config('database.connections.mysql');
    File::put($carpeta.'/.env', implode("\n", [
        'APP_NAME="Instancia de prueba"',
        'APP_ENV=local',
        'APP_KEY='.$llave,
        'APP_URL=http://casa.alojaflow.test',
        'DB_CONNECTION=mysql',
        'DB_HOST='.$base['host'],
        'DB_PORT='.$base['port'],
        'DB_DATABASE='.$base['database'],
        'DB_USERNAME='.$base['username'],
        'DB_PASSWORD="'.$base['password'].'"',
        'CACHE_STORE=array',
        'SESSION_DRIVER=array',
    ])."\n");

    return [$carpeta, $llave];
}

/**
 * Arranca la aplicación en otro proceso, como lo hace el agente: con un entorno limpio y la carpeta de la
 * instancia. Devuelve lo que ve por dentro.
 *
 * @param  array<string, string>  $agregar
 * @return array{0: int, 1: array<string, mixed>|null, 2: string}
 */
function arrancarComoInstancia(?string $carpeta, array $agregar = []): array
{
    $sonda = archivoTemporal('sonda.php');
    File::put($sonda, <<<'PHP'
        <?php
        declare(strict_types=1);
        require $argv[1].'/vendor/autoload.php';
        $app = require $argv[1].'/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $normal = fn (string $ruta): string => str_replace('\\', '/', $ruta);
        echo json_encode([
            'instancia' => App\Instalacion\Instancia::activa(),
            'storage' => $normal(storage_path()),
            'entorno' => $normal($app->environmentFilePath()),
            'configuracion' => $normal($app->getCachedConfigPath()),
            'rutas' => $normal($app->getCachedRoutesPath()),
            'nombre' => config('app.name'),
            'base' => config('database.connections.mysql.database'),
            'bloqueada' => app(App\Actualizacion\Actualizador::class)->bloqueadaPorInstancia(),
            'tareas' => array_keys(App\Programador\TareasPorVisita::tareas()),
            'en_el_entorno' => getenv('APP_KEY') !== false,
        ]);
        PHP);
    $entorno = [];
    foreach (array_keys(getenv() + $_ENV + array_filter($_SERVER, 'is_string')) as $nombre) {
        $nombre = (string) $nombre;
        $sistema = in_array(strtoupper($nombre), ['PATH', 'PATHEXT', 'SYSTEMROOT', 'WINDIR', 'COMSPEC', 'TEMP', 'TMP', 'TMPDIR', 'HOME'], true);
        $entorno[$nombre] = $sistema && getenv($nombre) !== false ? (string) getenv($nombre) : false;
    }
    $entorno['XDEBUG_MODE'] = 'off';
    if ($carpeta !== null) {
        $entorno[Instancia::VARIABLE] = $carpeta;
    }
    $resultado = Process::env(array_merge($entorno, $agregar))->timeout(120)->run([PHP_BINARY, $sonda, base_path()]);
    $salida = trim($resultado->output());
    $json = json_decode(substr($salida, (int) strrpos($salida, '{"instancia"')), true);

    return [(int) $resultado->exitCode(), is_array($json) ? $json : null, $resultado->errorOutput().$salida];
}

it('arranca como instancia con el .env, el storage y las cachés de su carpeta', function () {
    [$carpeta] = carpetaDeInstancia();

    [$codigo, $vista, $detalle] = arrancarComoInstancia($carpeta);

    expect($codigo)->toBe(0, $detalle)
        ->and($vista['instancia'])->toBeTrue()
        ->and($vista['storage'])->toBe($carpeta.'/storage')
        ->and($vista['entorno'])->toBe($carpeta.'/.env')
        ->and($vista['configuracion'])->toBe($carpeta.'/cache/config.php')
        ->and($vista['rutas'])->toBe($carpeta.'/cache/routes-v7.php')
        ->and($vista['nombre'])->toBe('Instancia de prueba')
        ->and($vista['base'])->toBe(config('database.connections.mysql.database'))
        // El código lo comparten todas: ninguna se actualiza sola ni desde su panel.
        ->and($vista['bloqueada'])->toBeTrue()
        ->and(array_intersect($vista['tareas'], TareasPorVisita::DE_ACTUALIZACION))->toBe([])
        // Su .env no pasa al entorno: el programador lanza cada tarea en otro proceso, que lo lee de la carpeta.
        ->and($vista['en_el_entorno'])->toBeFalse();
});

it('no arranca si el entorno trae la base o la llave de otro', function () {
    [$carpeta] = carpetaDeInstancia();

    [$codigo, , $detalle] = arrancarComoInstancia($carpeta, ['DB_DATABASE' => 'la_de_otro_cliente']);
    expect($codigo)->not->toBe(0)->and($detalle)->toContain('DB_DATABASE');

    [$codigo, , $detalle] = arrancarComoInstancia($carpeta, ['APP_KEY' => 'base64:'.base64_encode(random_bytes(32))]);
    expect($codigo)->not->toBe(0)->and($detalle)->toContain('APP_KEY');
});

it('acepta lo que heredó de un proceso de la misma instancia', function () {
    [$carpeta, $llave] = carpetaDeInstancia();

    [$codigo, $vista, $detalle] = arrancarComoInstancia($carpeta, ['APP_KEY' => $llave]);

    expect($codigo)->toBe(0, $detalle)->and($vista['instancia'])->toBeTrue();
});

it('no arranca si la carpeta no es de una instancia', function () {
    $carpeta = str_replace('\\', '/', dirname(archivoTemporal('x'))).'/no-es-instancia';
    File::ensureDirectoryExists($carpeta);

    [$codigo, , $detalle] = arrancarComoInstancia($carpeta);

    expect($codigo)->not->toBe(0)->and($detalle)->toContain(Instancia::VARIABLE);
});

it('sin la variable, una instalación normal sigue igual', function () {
    expect(Instancia::activa())->toBeFalse()
        ->and(TareasPorVisita::tareas())->toHaveKey('alojaflow:buscar-version');
});

it('alojaflow:instalar crea al super administrador sin contraseña conocida y no repite', function () {
    [$codigo, $respuesta] = comandoJson('alojaflow:instalar', ['--nombre' => 'Dueña', '--correo' => 'Duena@Example.com', '--idioma' => 'es']);

    expect($codigo)->toBe(0)
        ->and($respuesta['instalada'])->toBeTrue()
        ->and($respuesta['ya_estaba'])->toBeFalse()
        ->and($respuesta['enlace'])->toStartWith(rtrim((string) config('app.url'), '/').'/entrar/');
    $dueno = User::query()->where('email', 'duena@example.com')->firstOrFail();
    expect($dueno->es_superadministrador)->toBeTrue()
        ->and($dueno->activo)->toBeTrue()
        ->and(Role::query()->where('name', 'gerente')->exists())->toBeTrue()
        ->and(is_file(storage_path(Instalador::MARCA)))->toBeTrue();

    // Repetir el paso (AlojaFlow App reintenta) no cambia nada.
    [$codigo, $respuesta] = comandoJson('alojaflow:instalar', ['--nombre' => 'Otra', '--correo' => 'otra@example.com']);
    expect($codigo)->toBe(0)
        ->and($respuesta['ya_estaba'])->toBeTrue()
        ->and($respuesta)->not->toHaveKey('enlace')
        ->and(User::query()->count())->toBe(1);
});

it('alojaflow:instalar rechaza un idioma que no existe o un correo que no es', function () {
    [$codigo, $respuesta] = comandoJson('alojaflow:instalar', ['--nombre' => 'Dueña', '--correo' => 'duena@example.com', '--idioma' => 'xx']);
    expect($codigo)->not->toBe(0)->and($respuesta['instalada'])->toBeFalse();

    [$codigo, $respuesta] = comandoJson('alojaflow:instalar', ['--nombre' => 'Dueña', '--correo' => 'no-es-correo']);
    expect($codigo)->not->toBe(0)->and($respuesta['instalada'])->toBeFalse()
        ->and(User::query()->count())->toBe(0)
        ->and(is_file(storage_path(Instalador::MARCA)))->toBeFalse();
});

it('alojaflow:enlace-entrada da el enlace al super administrador, nunca a quien usa dos pasos', function () {
    $super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es']);

    [$codigo, $respuesta] = comandoJson('alojaflow:enlace-entrada', []);
    expect($codigo)->toBe(0)->and($respuesta['enlace'])->toContain('/entrar/');

    $super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    [$codigo, $respuesta] = comandoJson('alojaflow:enlace-entrada', []);
    expect($codigo)->not->toBe(0)->and($respuesta['enlace'])->toBeNull();

    [$codigo] = comandoJson('alojaflow:enlace-entrada', ['--correo' => 'nadie@example.com']);
    expect($codigo)->not->toBe(0);
});

it('en una instancia, alojaflow:enlace-entrada solo sirve mientras el dueño no ha creado su contraseña', function () {
    comoInstancia();
    $super = User::factory()->create(['es_administrador' => true, 'es_superadministrador' => true, 'activo' => true, 'idioma' => 'es', 'debe_crear_contrasena' => true]);

    [$codigo, $respuesta] = comandoJson('alojaflow:enlace-entrada', []);
    expect($codigo)->toBe(0)->and($respuesta['enlace'])->toContain('/entrar/');

    // Ya creó la suya: el equipo de AlojaFlow entra como soporte, con su permiso, nunca como el dueño.
    $super->forceFill(['password' => 'Una-clave-larga-y-propia-2026'])->save();
    [$codigo, $respuesta] = comandoJson('alojaflow:enlace-entrada', []);
    expect($codigo)->not->toBe(0)
        ->and($respuesta['enlace'])->toBeNull()
        ->and($respuesta['error'])->toBe(__('consola.enlace_entrada.ya_tiene_contrasena'));
});
