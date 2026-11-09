<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Copias\CifradoCopia;
use App\Copias\CopiaExterna;
use App\Copias\ErrorCopia;
use App\Copias\FirmaS3;
use App\Copias\GestorCopias;
use App\Sincronizacion\HotelDruid\ValidadorDestino;
use App\Support\Ajustes;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

afterEach(fn () => Ajustes::olvidar());

beforeEach(function () {
    // Sin red de verdad: el destino resuelve a una IP pública.
    $this->app->instance(ValidadorDestino::class, new class () extends ValidadorDestino {
        protected function resolver(string $host): array
        {
            return ['140.82.112.3'];
        }
    });
    $this->subidas = new ArrayObject();
    $this->falla = false;
    Http::fake(function (Request $peticion) {
        if ($this->falla) {
            return Http::response('No', 403);
        }
        $this->subidas->append(['url' => $peticion->url(), 'cuerpo' => $peticion->body(), 'auth' => $peticion->header('Authorization')[0] ?? '']);

        return Http::response('', 200);
    });
    $this->s3 = ['tipo' => 's3', 'endpoint' => 'https://objects-us-east-1.dream.io', 'region' => 'us-east-1', 'bucket' => 'respaldos-hotel', 'carpeta' => 'alojaflow', 'llave' => 'AKIAEJEMPLO'];
});

it('firma como AWS documenta (ejemplo oficial de GET Object)', function () {
    $autorizacion = FirmaS3::autorizacion('GET', '/test.txt', [], [
        'host' => 'examplebucket.s3.amazonaws.com',
        'range' => 'bytes=0-9',
        'x-amz-content-sha256' => 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
        'x-amz-date' => '20130524T000000Z',
    ], 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855', 'us-east-1', 'AKIAIOSFODNN7EXAMPLE', 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY');

    expect($autorizacion)->toBe('AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request,SignedHeaders=host;range;x-amz-content-sha256;x-amz-date,Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41');
});

it('cifra y descifra una copia, y no la abre con otra clave ni alterada', function () {
    $original = archivoTemporal('copia.zip');
    file_put_contents($original, random_bytes(2_500_000));
    $cifrada = $original.'.afcopia';
    $vuelta = $original.'.vuelta';

    CifradoCopia::cifrar($original, $cifrada, 'una clave larga de prueba');
    expect(CifradoCopia::esCifrada($cifrada))->toBeTrue()
        ->and(str_contains((string) file_get_contents($cifrada), substr((string) file_get_contents($original), 1000, 64)))->toBeFalse();

    CifradoCopia::descifrar($cifrada, $vuelta, 'una clave larga de prueba');
    expect(hash_file('sha256', $vuelta))->toBe(hash_file('sha256', $original));

    expect(fn () => CifradoCopia::descifrar($cifrada, $vuelta, 'otra clave'))->toThrow(ErrorCopia::class);
    $alterada = (string) file_get_contents($cifrada);
    $alterada[2000] = $alterada[2000] === 'a' ? 'b' : 'a';
    file_put_contents($cifrada, $alterada);
    expect(fn () => CifradoCopia::descifrar($cifrada, $vuelta, 'una clave larga de prueba'))->toThrow(ErrorCopia::class);
});

it('guarda el destino solo si la prueba de subida funciona, con los secretos cifrados', function () {
    $externa = app(CopiaExterna::class);

    $this->falla = true;
    expect(fn () => $externa->guardar($this->s3, 'secreto-s3', 'clave de cifrado larga'))->toThrow(ErrorCopia::class);
    expect($externa->configurada())->toBeFalse();

    $this->falla = false;
    $externa->guardar($this->s3, 'secreto-s3', 'clave de cifrado larga');
    Ajustes::olvidar();

    expect($externa->configurada())->toBeTrue()
        ->and($this->subidas[0]['url'])->toBe('https://objects-us-east-1.dream.io/respaldos-hotel/alojaflow/alojaflow-prueba.txt')
        ->and($this->subidas[0]['auth'])->toStartWith('AWS4-HMAC-SHA256 Credential=AKIAEJEMPLO/')
        ->and(json_encode(Ajustes::obtener(CopiaExterna::AJUSTE_SECRETO)))->not->toContain('secreto-s3');
});

it('la copia nocturna sale cifrada como la del día y, el día 1, también como la del mes', function () {
    $externa = app(CopiaExterna::class);
    $externa->guardar($this->s3, 'secreto-s3', 'clave de cifrado larga');
    $this->subidas->exchangeArray([]);
    $this->travelTo(now()->startOfMonth()->setTime(3, 10));

    $this->artisan('alojaflow:copia --programada')->assertSuccessful();

    $urls = array_column($this->subidas->getArrayCopy(), 'url');
    expect($urls)->toContain('https://objects-us-east-1.dream.io/respaldos-hotel/alojaflow/alojaflow-diaria-'.now()->isoWeekday().'.afcopia')
        ->and($urls)->toContain('https://objects-us-east-1.dream.io/respaldos-hotel/alojaflow/alojaflow-mensual-'.now()->format('m').'.afcopia')
        ->and(str_starts_with($this->subidas[0]['cuerpo'], CifradoCopia::MARCA))->toBeTrue()
        ->and($externa->ultima()['ok'])->toBeTrue();
});

it('si el destino falla, lo anota y la copia local se queda', function () {
    $externa = app(CopiaExterna::class);
    $externa->guardar(['tipo' => 'webdav', 'url' => 'https://nube.example.com/remote.php/dav/files/hotel', 'usuario' => 'hotel', 'carpeta' => 'copias'], 'contrasena-webdav', 'clave de cifrado larga');
    $this->falla = true;

    $this->artisan('alojaflow:copia --programada')->assertSuccessful();

    expect($externa->ultima()['ok'])->toBeFalse()
        ->and(app(GestorCopias::class)->listar())->toHaveCount(1);
});

it('una copia cifrada se descifra por consola con su clave', function () {
    $original = archivoTemporal('copia.zip');
    file_put_contents($original, 'contenido de la copia');
    CifradoCopia::cifrar($original, $original.'.afcopia', 'clave de cifrado larga');

    $this->artisan('alojaflow:descifrar-copia', ['archivo' => $original.'.afcopia', 'salida' => $original.'.descifrada'])
        ->expectsQuestion(__('copias.externa.clave'), 'clave de cifrado larga')
        ->assertSuccessful();

    expect(file_get_contents($original.'.descifrada'))->toBe('contenido de la copia');
});
