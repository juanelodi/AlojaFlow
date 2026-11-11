<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\LectorCopia;
use Tests\Support\ConstructorCopia;

/**
 * @return array{0: \App\Sincronizacion\HotelDruid\ResumenCopia, 1: array<string, list<array<string, string|null>>>}
 */
function leerCopia(ConstructorCopia $copia): array
{
    $filas = [];
    $resumen = (new LectorCopia())->leer(
        $copia->guardar(archivoTemporal('copia.php')),
        function (string $tabla, ?int $anio, array $fila) use (&$filas): void {
            $filas[$tabla.($anio ?? '')][] = $fila;
        },
    );

    return [$resumen, $filas];
}

function codigoDeError(callable $accion): ?CodigoError
{
    try {
        $accion();
    } catch (ErrorSincronizacion $e) {
        return $e->codigo;
    }

    return null;
}

it('lee la cabecera, el idioma y las tablas conservadas', function () {
    [$resumen, $filas] = leerCopia(ConstructorCopia::nueva()->unidad('Cabaña 1')->cliente(7, 'Pérez', 'Ana'));

    expect($resumen->version)->toBe('3.081')
        ->and($resumen->fechaCopia)->toBe('2026-11-03 10:15:00')
        ->and($resumen->idioma)->toBe('es')
        ->and($resumen->inicioSesionActivado)->toBeTrue()
        ->and($resumen->anios)->toBe([2026])
        ->and($filas['appartamenti'][0]['idappartamenti'])->toBe('Cabaña 1')
        ->and($filas['clienti'][0]['nome'])->toBe('Ana')
        ->and($filas['periodi2026'])->toHaveCount(365);
});

it('nunca entrega las tablas descartadas, como utenti con sus contraseñas', function () {
    [$resumen, $filas] = leerCopia(ConstructorCopia::nueva());

    expect($filas)->not->toHaveKey('utenti')
        ->and($resumen->filas)->not->toHaveKey('utenti');
});

it('deshace los escapes y conserva los valores de varias líneas', function () {
    $raro = "Línea 1 </cmp> y @%&@ y <riga> </riga> </righetabella>\r\nLínea 2 </cmp@%&@>";
    [, $filas] = leerCopia(ConstructorCopia::nueva()->cliente(1, 'Gómez', null, ['commento' => $raro]));

    expect($filas['clienti'][0]['commento'])->toBe(str_replace("\r\n", "\n", $raro));
});

it('trata el vacío como nulo', function () {
    [, $filas] = leerCopia(ConstructorCopia::nueva()->cliente(1, 'López'));

    expect($filas['clienti'][0]['nome'])->toBeNull()
        ->and($filas['clienti'][0]['cognome'])->toBe('López');
});

it('detecta una principal sin inicio de sesión', function () {
    [$resumen] = leerCopia(ConstructorCopia::nueva()->sinInicioSesion());

    expect($resumen->inicioSesionActivado)->toBeFalse();
});

it('rechaza una versión no admitida', function () {
    expect(codigoDeError(fn () => leerCopia(ConstructorCopia::nueva()->version('3.1'))))->toBe(CodigoError::VersionNoAdmitida);
});

it('rechaza una copia cortada', function () {
    expect(codigoDeError(fn () => leerCopia(ConstructorCopia::nueva()->cortada())))->toBe(CodigoError::CopiaIncompleta);
});

it('rechaza una copia sin una tabla anual obligatoria', function () {
    expect(codigoDeError(fn () => leerCopia(ConstructorCopia::nueva()->omitirTabla('soldi2026'))))->toBe(CodigoError::CopiaIncompleta);
});

it('rechaza un archivo sin la cabecera de HotelDruid', function () {
    $ruta = archivoTemporal('otra.php');
    file_put_contents($ruta, "<html>\n<body>Login</body>\n</html>");

    expect(codigoDeError(fn () => (new LectorCopia())->leer($ruta, fn () => null)))->toBe(CodigoError::FormatoInvalido);
});

it('rechaza una fila con menos valores que columnas', function () {
    $ruta = archivoTemporal('mala.php');
    $texto = ConstructorCopia::nueva()->texto();
    $texto = preg_replace('#<riga><cmp>1</cmp><cmp>3\.081</cmp></riga>#', '<riga><cmp>1</cmp></riga>', $texto, 1);
    file_put_contents($ruta, $texto);

    expect(codigoDeError(fn () => (new LectorCopia())->leer($ruta, fn () => null)))->toBe(CodigoError::FormatoInvalido);
});
