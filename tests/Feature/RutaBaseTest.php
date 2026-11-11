<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

/*
 * En el servicio alojado cada cliente vive en una ruta del mismo dominio (app.alojaflow.com/<cliente>, D-163). El
 * servidor web lo dice con SCRIPT_NAME, como en una instalación dentro de una carpeta del hosting.
 */

/**
 * @param  array<string, string>  $servidor
 */
function bajoRuta(string $ruta, array $servidor = []): Illuminate\Testing\TestResponse
{
    return test()->call('GET', 'http://localhost/casa'.$ruta, [], [], [], $servidor + [
        'SCRIPT_NAME' => '/casa/index.php',
        'PHP_SELF' => '/casa/index.php',
        'SCRIPT_FILENAME' => public_path('index.php'),
    ]);
}

it('arma todas sus direcciones con la ruta del cliente', function () {
    $html = (string) bajoRuta('/login')->assertOk()->getContent();

    // Ninguna dirección propia sin la ruta: ni completa ni desde la raíz del dominio.
    expect(preg_match_all('#https?://localhost/(?!casa/)#', $html))->toBe(0)
        ->and(preg_match_all('#\b(?:href|src|action|data-[a-z-]+)="/(?!/)#', $html))->toBe(0)
        ->and($html)->toContain('http://localhost/casa/')
        // Lo que se guarda en el navegador tampoco se mezcla con lo de otro cliente.
        ->and($html)->toContain("'alojaflow\\/casa.'");
});

it('deja sus galletas en la ruta del cliente', function () {
    config(['session.path' => '/casa']);
    $respuesta = bajoRuta('/login')->assertOk();

    expect($respuesta->getCookie('XSRF-TOKEN', false)?->getPath())->toBe('/casa')
        ->and($respuesta->getCookie((string) config('session.cookie'), false)?->getPath())->toBe('/casa');
});

it('sin ruta, las llaves del navegador no cambian', function () {
    $html = (string) $this->get('/login')->assertOk()->getContent();

    expect($html)->toContain("window.alojaFlowLlave = (nombre) => 'alojaflow.' + nombre")
        ->and($html)->not->toContain('alojaflow\\/');
});
