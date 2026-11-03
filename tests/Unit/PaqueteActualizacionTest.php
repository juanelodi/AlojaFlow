<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Actualizacion\Empaquetador;
use App\Actualizacion\Firma;
use App\Actualizacion\Manifiesto;
use App\Actualizacion\Rutas;

it('admite en un paquete solo rutas relativas y seguras', function (string $ruta, bool $valida) {
    expect(Rutas::valida($ruta))->toBe($valida);
})->with([
    ['app/Models/Reserva.php', true],
    ['vendor/laravel/framework/src/Illuminate/Foundation/Application.php', true],
    ['.env.example', true],
    ['', false],
    ['/etc/passwd', false],
    ['../fuera.php', false],
    ['app/../../fuera.php', false],
    ['app//doble.php', false],
    ['app/./punto.php', false],
    ['app\\Windows.php', false],
    ['app/con espacio.php', false],
    ['C:/Windows/win.ini', false],
    ['app/carpeta./truco.php', false],
    [str_repeat('a', 241), false],
]);

it('nunca toca la configuración ni los datos de la instalación', function (string $ruta, bool $protegida) {
    expect(Rutas::protegida($ruta))->toBe($protegida);
})->with([
    ['.env', true],
    ['.ENV', true],
    ['.env.production', true],
    ['.env.example', false],
    ['storage/app/private/x.zip', true],
    ['Storage/app/x', true],
    ['bootstrap/cache/packages.php', true],
    ['bootstrap/app.php', false],
    ['public/storage', true],
    ['.git/config', true],
    ['app/Models/User.php', false],
    ['vendor/autoload.php', false],
]);

it('lee un manifiesto válido y rechaza los que no lo son', function () {
    $hash = str_repeat('a', 64);
    $manifiesto = Manifiesto::desdeJson((string) json_encode(['producto' => 'AlojaFlow', 'version' => '1.0.2', 'archivos' => ['app/A.php' => $hash]]));
    expect($manifiesto->version)->toBe('1.0.2')
        ->and($manifiesto->archivos)->toBe(['app/A.php' => $hash])
        ->and($manifiesto->phpMinimo)->toBe('8.3.0');

    foreach ([
        ['producto' => 'Otro', 'version' => '1.0.2', 'archivos' => ['app/A.php' => $hash]],
        ['producto' => 'AlojaFlow', 'version' => '1.0', 'archivos' => ['app/A.php' => $hash]],
        ['producto' => 'AlojaFlow', 'version' => '1.0.2', 'archivos' => []],
        ['producto' => 'AlojaFlow', 'version' => '1.0.2', 'archivos' => ['../A.php' => $hash]],
        ['producto' => 'AlojaFlow', 'version' => '1.0.2', 'archivos' => ['app/A.php' => 'corto']],
        ['producto' => 'AlojaFlow', 'version' => '1.0.2', 'archivos' => ['manifiesto.json' => $hash]],
    ] as $datos) {
        expect(fn () => Manifiesto::desdeJson((string) json_encode($datos)))->toThrow(InvalidArgumentException::class);
    }
});

it('firma el mismo JSON sea cual sea el orden de los archivos', function () {
    $hash = str_repeat('b', 64);
    $uno = new Manifiesto('1.0.2', '2026-11-03', '8.3.0', ['zip'], ['b.php' => $hash, 'a.php' => $hash]);
    $otro = new Manifiesto('1.0.2', '2026-11-03', '8.3.0', ['zip'], ['a.php' => $hash, 'b.php' => $hash]);

    expect($uno->aJson())->toBe($otro->aJson())
        ->and(Manifiesto::desdeJson($uno->aJson())->archivos)->toBe(['a.php' => $hash, 'b.php' => $hash]);
});

it('firma y verifica con Ed25519', function () {
    $claves = Firma::generarClaves();
    $firma = Firma::firmar('mensaje', $claves['secreta']);

    expect(Firma::verificar('mensaje', $firma, $claves['publica']))->toBeTrue()
        ->and(Firma::verificar('mensaje alterado', $firma, $claves['publica']))->toBeFalse()
        ->and(Firma::verificar('mensaje', $firma, Firma::generarClaves()['publica']))->toBeFalse()
        ->and(Firma::verificar('mensaje', 'basura', $claves['publica']))->toBeFalse();
});

it('exige que la clave secreta sea la pareja de la pública', function () {
    $claves = Firma::generarClaves();

    expect(fn () => Empaquetador::comprobarClaves($claves['secreta'], $claves['publica']))->not->toThrow(RuntimeException::class)
        ->and(fn () => Empaquetador::comprobarClaves($claves['secreta'], Firma::generarClaves()['publica']))->toThrow(RuntimeException::class)
        ->and(fn () => Empaquetador::comprobarClaves('no-es-una-clave', $claves['publica']))->toThrow(RuntimeException::class);
});
