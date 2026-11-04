<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Todas las claves de un arreglo de textos: ['a' => ['b' => 'x']] da ['a.b' => 'x'].
 *
 * @param  array<string, mixed>  $textos
 * @return array<string, string>
 */
function aplanarTextos(array $textos, string $prefijo = ''): array
{
    $planos = [];
    foreach ($textos as $clave => $valor) {
        if (is_array($valor)) {
            $planos += aplanarTextos($valor, $prefijo.$clave.'.');
        } else {
            $planos[$prefijo.$clave] = (string) $valor;
        }
    }

    return $planos;
}

/**
 * @return list<string> las variables de un texto, sin repetir y ordenadas
 */
function variablesDe(string $texto): array
{
    preg_match_all('/:[a-z_]+|\{[a-z_]+\}/', $texto, $coincidencias);
    $variables = array_values(array_unique($coincidencias[0]));
    sort($variables);

    return $variables;
}

/**
 * @return array<string, string>
 */
function textosDe(string $idioma, string $archivo): array
{
    $ruta = dirname(__DIR__, 2)."/lang/$idioma/$archivo.php";

    return is_file($ruta) ? aplanarTextos(require $ruta) : [];
}

// Los mensajes de validación en inglés ya los trae Laravel.
$archivos = array_values(array_diff(array_map(fn (string $ruta): string => basename($ruta, '.php'), glob(dirname(__DIR__, 2).'/lang/es/*.php') ?: []), ['validation']));

// El inglés es el idioma de respaldo: debe estar completo.
it('el inglés tiene todo lo que tiene el español', function (string $archivo) {
    $faltan = array_keys(array_diff_key(textosDe('es', $archivo), textosDe('en', $archivo)));

    expect($faltan)->toBe([]);
})->with($archivos);

// Lo que ve el huésped va completo en todos los idiomas; lo demás cae al inglés hasta traducirlo.
it('lo que ve el huésped está en todos los idiomas', function (string $idioma) {
    $publicos = [
        'motor' => fn (string $clave): bool => ! str_starts_with($clave, 'panel.'),
        'llegada' => fn (string $clave): bool => ! preg_match('/^(panel|privacidad|documentos)\./', $clave),
        'documentos' => fn (string $clave): bool => true,
        'mensajes' => fn (string $clave): bool => str_starts_with($clave, 'base.'),
        'legal' => fn (string $clave): bool => ! str_starts_with($clave, 'mexico.'),
    ];
    foreach ($publicos as $archivo => $esPublica) {
        $claves = array_keys(array_filter(textosDe('es', $archivo), $esPublica, ARRAY_FILTER_USE_KEY));
        $faltan = array_values(array_diff($claves, array_keys(textosDe($idioma, $archivo))));
        expect($faltan)->toBe([], "Faltan en lang/$idioma/$archivo.php");
    }
})->with(['fr', 'it', 'pt', 'de']);

it('cada traducción conserva sus variables', function (string $idioma) {
    foreach (glob(dirname(__DIR__, 2).'/lang/es/*.php') ?: [] as $ruta) {
        $archivo = basename($ruta, '.php');
        if ($archivo === 'validation') {
            continue;
        }
        $origen = textosDe('es', $archivo);
        foreach (textosDe($idioma, $archivo) as $clave => $texto) {
            if (isset($origen[$clave])) {
                expect(variablesDe($texto))->toBe(variablesDe($origen[$clave]), "$idioma/$archivo.$clave");
            }
        }
    }
})->with(['en', 'fr', 'it', 'pt', 'de']);
