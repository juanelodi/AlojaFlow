<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\LectorCopia;
use App\Sincronizacion\HotelDruid\RespuestaPrincipal;
use Tests\Support\ConstructorCopia;

/*
 * Las validaciones de subordinacion.md B8 que no están en LectorCopiaTest, y cómo se reconoce lo que contesta la principal (A3).
 */

/**
 * @return array{0: App\Sincronizacion\HotelDruid\ResumenCopia|null, 1: CodigoError|null, 2: array<string, list<array<string, string|null>>>}
 */
function leerTextoDeCopia(string $texto): array
{
    $ruta = archivoTemporal('copia.php');
    file_put_contents($ruta, $texto);
    $filas = [];
    try {
        $resumen = (new LectorCopia())->leer($ruta, function (string $tabla, ?int $anio, array $fila) use (&$filas): void {
            $filas[$tabla.($anio ?? '')][] = $fila;
        });

        return [$resumen, null, $filas];
    } catch (ErrorSincronizacion $error) {
        return [null, $error->codigo, $filas];
    }
}

it('lee igual una copia con finales de línea de Windows', function () {
    [$resumen, , $filas] = leerTextoDeCopia(str_replace("\n", "\r\n", ConstructorCopia::nueva()->cliente(1, 'Gómez', 'Luz', ['commento' => "Uno\nDos"])->texto()));

    expect($resumen?->version)->toBe('3.081')->and($filas['clienti'][0]['commento'])->toBe("Uno\nDos");
});

it('sustituye lo que no es UTF-8 y lo cuenta', function () {
    [$resumen, , $filas] = leerTextoDeCopia(ConstructorCopia::nueva()->cliente(1, "Mu\xF1oz")->texto());

    expect($filas['clienti'][0]['cognome'])->toBe("Mu\u{FFFD}oz")->and($resumen?->incidencias)->toBe(['valores_no_utf8' => 1]);
});

it('rechaza tablas de un año que la principal no tiene', function () {
    $ajena = "<tabella>\n<nometabella>costi2031</nometabella>\n<colonnetabella>\n<nomecolonna>idcosti</nomecolonna>\n<tipocolonna>int</tipocolonna>\n</colonnetabella>\n<righetabella>\n</righetabella>\n</tabella>\n</database>";

    expect(leerTextoDeCopia(str_replace('</database>', $ajena, ConstructorCopia::nueva()->texto()))[1])->toBe(CodigoError::FormatoInvalido);
});

it('rechaza nombres de tabla o de columna que no son de HotelDruid', function (string $bueno, string $malo) {
    expect(leerTextoDeCopia(str_replace($bueno, $malo, ConstructorCopia::nueva()->texto()))[1])->toBe(CodigoError::FormatoInvalido);
})->with([
    ['<nometabella>casse</nometabella>', '<nometabella>casse; drop</nometabella>'],
    ['<nomecolonna>nome_cassa</nomecolonna>', '<nomecolonna>nome cassa</nomecolonna>'],
]);

it('rechaza una línea más larga de lo permitido, sin cargarla entera', function () {
    config(['alojaflow.subordinacion.tamano_maximo_linea' => 100000]);

    expect(leerTextoDeCopia(ConstructorCopia::nueva()->cliente(1, 'Largo', null, ['commento' => str_repeat('x', 300000)])->texto())[1])->toBe(CodigoError::FormatoInvalido);
});

it('no guarda nada de los archivos de configuración salvo el idioma', function () {
    [$resumen, , $filas] = leerTextoDeCopia(ConstructorCopia::nueva()->texto());

    expect(json_encode([$resumen, $filas]))->not->toContain('secreto-inventado')->not->toContain('clave-inventada-de-api')->not->toContain('hash-inventado');
});

describe('lo que contesta la principal', function () {
    it('el formulario de acceso manda sobre la cabecera, que también lo trae', function () {
        $pagina = new RespuestaPrincipal(200, '<html><style>:root { --t1color: #b9ccd4; }</style><form><input name="password_phpr" type="password"></form></html>');

        expect($pagina->pideCredenciales())->toBeTrue()->and($pagina->soloCabecera())->toBeTrue();
    });

    it('el extracto es texto visible, corto y con el estado si fue un error', function () {
        $pagina = new RespuestaPrincipal(503, '<html><head><script>a()</script><!-- nota --></head><body><h1>Servicio&nbsp;no disponible</h1>'.str_repeat(' más', 400).'</body></html>');

        expect($pagina->extracto())->toStartWith('HTTP 503: Servicio no disponible más')->and(mb_strlen($pagina->extracto()))->toBe(500);
    });

    it('una página que solo dice que no hay copias no es un borrado fallido', function () {
        expect((new RespuestaPrincipal(200, '<html>No hay ninguna copia guardada. Crear copia. Borrar todo.</html>'))->noPudoBorrar())->toBeFalse()
            ->and((new RespuestaPrincipal(200, '<html>No se ha podido borrar el archivo de la copia.</html>'))->noPudoBorrar())->toBeTrue();
    });

    it('de una redirección solo se enseña la dirección, sin su consulta', function () {
        expect((new RespuestaPrincipal(302, '', 'https://otro.example/inizio.php?password_phpr=x#a'))->destinoRedireccion())->toBe('https://otro.example/inizio.php');
    });
});
