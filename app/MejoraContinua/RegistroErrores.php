<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use App\Seguridad\Acceso;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anota los errores que Laravel reporta (bootstrap/app.php) para mandarlos cada hora, ya saneados y
 * juntos: el mismo error el mismo día cuenta una vez con su número de veces. De una consulta SQL se
 * guarda el SQL con «?», nunca sus valores. Nunca lanza: un error al anotar un error no debe tapar el primero.
 */
final class RegistroErrores
{
    private static bool $anotando = false;

    public static function anotar(Throwable $error): void
    {
        if (self::$anotando || ! Permiso::activa()) {
            return;
        }
        self::$anotando = true;
        try {
            self::guardar($error);
        } catch (Throwable) {
            // Sin base de datos, o la tabla todavía no existe (a mitad de una actualización): se pierde este.
        } finally {
            self::$anotando = false;
        }
    }

    private static function guardar(Throwable $error): void
    {
        $mensaje = $error instanceof QueryException
            ? 'SQLSTATE['.$error->getCode().'] '.$error->getSql()
            : $error->getMessage();
        $mensaje = Saneador::texto($mensaje);
        $traza = self::traza($error);
        $archivo = Saneador::ruta($error->getFile());
        $funcion = $traza[0]['funcion'] ?? null;
        $version = (string) config('alojaflow.version');
        $huella = hash('sha256', implode("\n", [$error::class, $archivo, $error->getLine(), Saneador::plantilla($mensaje)]));
        $ahora = now('UTC');

        $filas = DB::table('mejora_errores')->where(['huella' => $huella, 'fecha' => $ahora->toDateString(), 'version' => $version])
            ->update(['veces' => DB::raw('veces + 1'), 'ultima_vez' => $ahora, 'actualizado_en' => $ahora]);
        if ($filas > 0) {
            return;
        }
        DB::table('mejora_errores')->insertOrIgnore([
            'huella' => $huella,
            'fecha' => $ahora->toDateString(),
            'version' => $version,
            'clase' => mb_substr($error::class, 0, 255),
            'mensaje' => $mensaje,
            'codigo' => Saneador::corto((string) $error->getCode(), 40),
            'archivo' => $archivo,
            'linea' => $error->getLine(),
            'funcion' => $funcion,
            'traza' => json_encode($traza),
            'contexto' => json_encode(self::contexto()),
            'veces' => 1,
            'primera_vez' => $ahora,
            'ultima_vez' => $ahora,
            'creado_en' => $ahora,
            'actualizado_en' => $ahora,
        ]);
    }

    /**
     * Archivo, línea y función de cada marco; nunca los argumentos.
     *
     * @return list<array{archivo: ?string, linea: ?int, funcion: ?string}>
     */
    private static function traza(Throwable $error): array
    {
        $traza = [];
        foreach (array_slice($error->getTrace(), 0, 40) as $marco) {
            $funcion = isset($marco['class']) ? $marco['class'].'::'.$marco['function'] : $marco['function'];
            $traza[] = [
                'archivo' => Saneador::ruta(isset($marco['file']) ? (string) $marco['file'] : null),
                'linea' => isset($marco['line']) ? (int) $marco['line'] : null,
                'funcion' => Saneador::corto($funcion),
            ];
        }

        return $traza;
    }

    /**
     * Dónde estaba la aplicación: pantalla, método, comando, rol e idioma. Nunca quién.
     *
     * @return array<string, string|bool>
     */
    private static function contexto(): array
    {
        if (app()->runningInConsole()) {
            $argumentos = (array) ($_SERVER['argv'] ?? []);

            return ['consola' => true, 'comando' => Saneador::corto(is_string($argumentos[1] ?? null) ? $argumentos[1] : null, 100) ?? 'artisan', 'idioma' => app()->getLocale()];
        }
        $peticion = request();
        $usuario = Acceso::usuarioActual();
        $componente = $peticion->header('X-Livewire') !== null ? 'livewire' : null;

        return array_filter([
            'consola' => false,
            'ruta' => Saneador::corto($peticion->route()?->getName(), 150),
            'metodo' => $peticion->method(),
            'componente' => $componente,
            'rol' => $usuario === null ? null : ($usuario->es_administrador ? 'administrador' : 'personal'),
            'idioma' => app()->getLocale(),
        ], fn (mixed $valor): bool => $valor !== null);
    }
}
