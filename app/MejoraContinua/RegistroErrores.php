<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
        self::conCuidado(function () use ($error): void {
            $mensaje = $error instanceof QueryException
                ? 'SQLSTATE['.$error->getCode().'] '.$error->getSql()
                : $error->getMessage();
            $traza = self::traza($error);
            self::guardar($error::class, $mensaje, (string) $error->getCode(), Saneador::ruta($error->getFile()), $error->getLine(), $traza, self::contexto());
        });
    }

    /**
     * Un error de JavaScript en el navegador de alguien del equipo (pantalla en blanco, botón que no responde).
     *
     * @param  array{tipo: string, mensaje: string, archivo: ?string, linea: ?int, pila: ?string, pantalla: ?string}  $datos
     */
    public static function anotarNavegador(array $datos): void
    {
        self::conCuidado(function () use ($datos): void {
            $traza = [];
            foreach (array_slice(preg_split('/\R/', (string) $datos['pila']) ?: [], 0, 20) as $renglon) {
                if (preg_match('/(?:at\s+)?(.*?)\s*\(?((?:https?:\/\/[^\s)]+|\/[^\s)]+)):(\d+)(?::\d+)?\)?$/', trim($renglon), $c) === 1) {
                    $traza[] = ['archivo' => Saneador::ruta((string) parse_url($c[2], PHP_URL_PATH)), 'linea' => (int) $c[3], 'funcion' => Saneador::corto($c[1] !== '' ? $c[1] : null)];
                }
            }
            $pantalla = preg_replace('/\d+/', '{n}', (string) parse_url((string) $datos['pantalla'], PHP_URL_PATH));
            self::guardar(
                'JavaScript\\'.(preg_match('/^[A-Za-z]{1,40}$/', $datos['tipo']) === 1 ? $datos['tipo'] : 'Error'),
                $datos['mensaje'],
                null,
                Saneador::ruta($datos['archivo'] === null ? null : (string) parse_url($datos['archivo'], PHP_URL_PATH)),
                $datos['linea'],
                $traza,
                array_filter(['origen' => 'navegador', 'ruta' => Saneador::corto($pantalla, 150)] + self::contexto(), fn (mixed $v): bool => $v !== null),
            );
        });
    }

    /**
     * Un problema que el sistema anotó en su registro sin que fuera una excepción (una consulta que no
     * respondió, una copia que no salió).
     */
    public static function anotarProblema(string $nivel, string $mensaje): void
    {
        self::conCuidado(fn () => self::guardar('Registro\\'.ucfirst($nivel), $mensaje, null, null, null, [], ['origen' => 'registro'] + self::contexto()));
    }

    private static function conCuidado(callable $anotar): void
    {
        if (self::$anotando || ! Permiso::activa()) {
            return;
        }
        self::$anotando = true;
        try {
            $anotar();
        } catch (Throwable) {
            // Sin base de datos, o la tabla todavía no existe (a mitad de una actualización): se pierde este.
        } finally {
            self::$anotando = false;
        }
    }

    /**
     * @param  list<array{archivo: ?string, linea: ?int, funcion: ?string}>  $traza
     * @param  array<string, string|bool>  $contexto
     */
    private static function guardar(string $clase, string $mensaje, ?string $codigo, ?string $archivo, ?int $linea, array $traza, array $contexto): void
    {
        $mensaje = Saneador::texto($mensaje);
        $funcion = $traza[0]['funcion'] ?? null;
        $version = (string) config('alojaflow.version');
        $huella = hash('sha256', implode("\n", [$clase, $archivo, $linea, Saneador::plantilla($mensaje)]));
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
            'clase' => mb_substr($clase, 0, 255),
            'mensaje' => $mensaje,
            'codigo' => Saneador::corto($codigo, 40),
            'archivo' => $archivo,
            'linea' => $linea,
            'funcion' => $funcion,
            'traza' => json_encode($traza),
            'contexto' => json_encode($contexto),
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
