<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Enums\ConEtiqueta;
use Illuminate\Support\Facades\Lang;

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
 * @return list<string> las variables de un texto, sin repetir y ordenadas; :Attribute y :attribute son la misma
 */
function variablesDe(string $texto): array
{
    preg_match_all('/:[a-zA-Z_]+|\{[a-z_]+\}/', $texto, $coincidencias);
    $variables = array_values(array_unique(array_map(strtolower(...), $coincidencias[0])));
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

/**
 * @return list<string> los archivos de lang/es
 */
function archivosDeTextos(): array
{
    return array_map(fn (string $ruta): string => basename($ruta, '.php'), glob(dirname(__DIR__, 2).'/lang/es/*.php') ?: []);
}

// El inglés es el idioma de respaldo: debe estar completo. Los mensajes de validación en inglés ya los trae Laravel.
it('el inglés tiene todo lo que tiene el español', function (string $archivo) {
    $faltan = array_keys(array_diff_key(textosDe('es', $archivo), textosDe('en', $archivo)));

    expect($faltan)->toBe([]);
})->with(array_values(array_diff(archivosDeTextos(), ['validation'])));

// Los textos legales de México solo existen en español e inglés; en otro idioma se parte de los genéricos (D-052).
it('cada idioma tiene todo lo que tiene el español', function (string $idioma) {
    foreach (archivosDeTextos() as $archivo) {
        $faltan = array_keys(array_diff_key(textosDe('es', $archivo), textosDe($idioma, $archivo)));
        $faltan = array_values(array_filter($faltan, fn (string $clave): bool => ! ($archivo === 'legal' && str_starts_with($clave, 'mexico.'))));
        expect($faltan)->toBe([], "Faltan en lang/$idioma/$archivo.php");
    }
})->with(['fr', 'it', 'pt', 'de']);

it('ningún idioma conserva claves que el español ya no tiene', function (string $idioma) {
    foreach (archivosDeTextos() as $archivo) {
        $sobran = array_keys(array_diff_key(textosDe($idioma, $archivo), textosDe('es', $archivo)));
        expect($sobran)->toBe([], "Sobran en lang/$idioma/$archivo.php");
    }
})->with(['en', 'fr', 'it', 'pt', 'de']);

it('cada traducción conserva sus variables', function (string $idioma) {
    foreach (archivosDeTextos() as $archivo) {
        $origen = textosDe('es', $archivo);
        foreach (textosDe($idioma, $archivo) as $clave => $texto) {
            if (isset($origen[$clave])) {
                expect(variablesDe($texto))->toBe(variablesDe($origen[$clave]), "$idioma/$archivo.$clave");
            }
        }
    }
})->with(['en', 'fr', 'it', 'pt', 'de']);

it('cada traducción conserva sus formas de plural', function (string $idioma) {
    foreach (archivosDeTextos() as $archivo) {
        $origen = textosDe('es', $archivo);
        foreach (textosDe($idioma, $archivo) as $clave => $texto) {
            if (isset($origen[$clave])) {
                expect(substr_count($texto, '|'))->toBe(substr_count($origen[$clave], '|'), "$idioma/$archivo.$clave");
            }
        }
    }
})->with(['en', 'fr', 'it', 'pt', 'de']);

// __() busca por puntos: 'a.b' => 'x' está en el archivo, pero no se encuentra y en pantalla sale la clave cruda.
it('la etiqueta de cada enum se encuentra', function (string $idioma) {
    foreach (glob(app_path('Enums/*.php')) ?: [] as $ruta) {
        $enum = 'App\\Enums\\'.basename($ruta, '.php');
        if (! enum_exists($enum) || ! in_array(ConEtiqueta::class, class_uses($enum), true)) {
            continue;
        }
        foreach ($enum::cases() as $caso) {
            $clave = 'enums.'.class_basename($enum).'.'.$caso->value;
            expect(Lang::has($clave, $idioma, false))->toBeTrue("Falta $idioma: $clave");
        }
    }
})->with(['es', 'en', 'fr', 'it', 'pt', 'de']);

it('toda clave citada en el código existe', function () {
    $faltan = [];
    foreach (['app', 'resources/views'] as $carpeta) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($carpeta), FilesystemIterator::SKIP_DOTS)) as $archivo) {
            if (! str_ends_with($archivo->getPathname(), '.php')) {
                continue;
            }
            preg_match_all("/\b(?:__|trans_choice)\(\s*'([a-z_]+\.[A-Za-z0-9_.\-]+)'\s*[,)]/", (string) file_get_contents($archivo->getPathname()), $coincidencias);
            foreach ($coincidencias[1] as $clave) {
                if (in_array(explode('.', $clave)[0], archivosDeTextos(), true) && ! Lang::has($clave, 'es', false)) {
                    $faltan[] = $clave.' en '.$archivo->getFilename();
                }
            }
        }
    }

    expect($faltan)->toBe([]);
});

// Las ayudas citan menús, botones y estados: deben decirlo igual que la pantalla, o quien lee no los encuentra.
it('lo que un texto cita de otro coincide', function (string $idioma) {
    $citas = [
        ['ui.calendario.sin_unidades', 'ui.nav.configuracion'],
        ['ui.calendario.sin_unidades', 'ui.unidades.modelos'],
        ['ui.estado.copias_vieja', 'ui.nav.sistema'],
        ['ui.estado.copias_vieja', 'copias.titulo'],
        ['ui.estado.correo_ayuda', 'ui.nav.configuracion'],
        ['ui.estado.correo_ayuda', 'mensajes.correo.titulo'],
        ['ui.unidades.tarifa_ayuda', 'ui.tarifas.modelos'],
        ['ui.resumen.sin_propiedades_texto', 'ui.propiedades.modelos'],
        ['dinero.facturas.sin_entidad', 'ui.nav.configuracion'],
        ['avisos.ical_falla', 'ical.fuentes.modelos'],
        ['avisos.choques', 'ical.choques.modelos'],
        ['avisos.envios_fallidos', 'mensajes.envios.modelos'],
        ['mensajes.automatizaciones.ayuda', 'mensajes.envios.modelos'],
        ['mensajes.mandar.manual', 'mensajes.envios.modelos'],
        ['copias.subir_ayuda', 'copias.restaurar'],
        ['motor.panel.confirmacion_inmediata_ayuda', 'enums.EstadoReserva.sin_confirmar'],
        ['limpieza.inspeccion_ayuda', 'enums.EstadoLimpieza.inspeccion'],
        ['sincronizacion.destino.red_local', 'ui.propiedades.red_local'],
        ['llegada.privacidad.anonimizar_ayuda', 'llegada.privacidad.palabra'],
        ['llegada.privacidad.anonimizar_ayuda_sub', 'llegada.privacidad.palabra'],
    ];
    $texto = function (string $clave) use ($idioma): string {
        [$archivo, $resto] = explode('.', $clave, 2);

        return textosDe($idioma, $archivo)[$resto];
    };
    foreach ($citas as [$quien, $citado]) {
        expect($texto($quien))->toContain($texto($citado));
    }
})->with(['es', 'en', 'fr', 'it', 'pt', 'de']);
