<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

use App\Sincronizacion\HotelDruid\CodigoError;
use App\Sincronizacion\HotelDruid\ErrorSincronizacion;
use App\Sincronizacion\HotelDruid\ValidadorDestino;

function validadorCon(string $ip): ValidadorDestino
{
    return new class ($ip) extends ValidadorDestino {
        public function __construct(private readonly string $ip)
        {
        }

        protected function resolver(string $host): array
        {
            return [$this->ip];
        }
    };
}

function errorDeDestino(ValidadorDestino $validador, string $url, bool $redLocal = false): ?CodigoError
{
    try {
        $validador->validar($url, $redLocal);
    } catch (ErrorSincronizacion $e) {
        return $e->codigo;
    }

    return null;
}

it('acepta https hacia una dirección pública', function () {
    $destino = validadorCon('93.184.215.14')->validar('https://principal.example/hoteldruid/', false);

    expect($destino)->toBe(['host' => 'principal.example', 'puerto' => 443, 'ip' => '93.184.215.14']);
});

it('rechaza http sin red local', function () {
    expect(errorDeDestino(validadorCon('93.184.215.14'), 'http://principal.example/hd/'))->toBe(CodigoError::DestinoNoPermitido);
});

it('rechaza direcciones privadas y de bucle local sin red local', function (string $ip) {
    expect(errorDeDestino(validadorCon($ip), 'https://principal.example/hd/'))->toBe(CodigoError::DestinoNoPermitido);
})->with(['127.0.0.1', '10.0.0.5', '192.168.1.20', '169.254.169.254', '172.16.0.1', '100.100.100.200', '192.0.0.192', '198.18.0.1', '::ffff:127.0.0.1', '::1', 'fd00::1']);

it('acepta localhost por http con red local', function () {
    expect(errorDeDestino(validadorCon('127.0.0.1'), 'http://localhost:8080/', true))->toBeNull();
});

it('exige la barra final y rechaza credenciales o consultas en la URL', function (string $url) {
    expect(errorDeDestino(validadorCon('93.184.215.14'), $url))->toBe(CodigoError::DestinoNoPermitido);
})->with([
    'https://principal.example/hd',
    'https://usuario:clave@principal.example/hd/',
    'https://principal.example/hd/?a=1',
]);
