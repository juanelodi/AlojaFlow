<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Instalacion;

use Dotenv\Dotenv;
use Illuminate\Support\Env;
use RuntimeException;

/**
 * El servicio alojado (D-162): cada cliente es una carpeta con su .env, su storage y sus cachés de arranque, y el
 * código de una versión lo comparten todas. Lo pide quien arranca la aplicación (el servidor web o el agente de
 * AlojaFlow App) con ALOJAFLOW_INSTANCIA. Sin esa variable, una instalación normal no cambia en nada.
 */
final class Instancia
{
    public const VARIABLE = 'ALOJAFLOW_INSTANCIA';

    /**
     * Las que dirían otra base u otra llave. Laravel no reemplaza una variable que ya está en el entorno: si el
     * proceso las trae de otro lado, la instancia usaría los datos de otro.
     */
    private const AJENAS = ['APP_KEY', 'DB_URL', 'DB_HOST', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    /** Las cachés de arranque que llevan secretos o la dirección: son de cada instancia. */
    private const CACHES = [
        'APP_CONFIG_CACHE' => 'config.php',
        'APP_ROUTES_CACHE' => 'routes-v7.php',
        'APP_EVENTS_CACHE' => 'events.php',
    ];

    private static ?string $carpeta = null;

    /**
     * Lo llama bootstrap/app.php antes de crear la aplicación. Si algo no cuadra, no arranca: mejor un error que
     * atender con los datos de otro cliente.
     *
     * @return string|null la carpeta de la instancia, o null en una instalación normal
     */
    public static function preparar(): ?string
    {
        self::$carpeta = null;
        $pedida = self::leer(self::VARIABLE);
        if ($pedida === null) {
            return null;
        }
        $real = realpath($pedida);
        if ($real === false || ! is_file($real.'/.env') || ! is_dir($real.'/storage') || ! is_dir($real.'/cache')) {
            throw new RuntimeException(self::VARIABLE.' no apunta a la carpeta de una instancia (con .env, storage y cache).');
        }
        $carpeta = str_replace('\\', '/', $real);
        self::comprobarEntorno($carpeta);

        foreach (self::CACHES as $variable => $archivo) {
            $_SERVER[$variable] = $_ENV[$variable] = $carpeta.'/cache/'.$archivo;
        }
        $_SERVER['LARAVEL_STORAGE_PATH'] = $_ENV['LARAVEL_STORAGE_PATH'] = $carpeta.'/storage';
        // Sin putenv, el .env de la instancia no pasa al entorno del proceso ni a los procesos que lance (el
        // programador corre cada tarea en otro): cada uno lo lee de su carpeta.
        Env::disablePutenv();
        self::$carpeta = $carpeta;

        return $carpeta;
    }

    public static function activa(): bool
    {
        return self::$carpeta !== null;
    }

    public static function carpeta(): ?string
    {
        return self::$carpeta;
    }

    /**
     * Una variable ajena solo se acepta si dice lo mismo que el .env de la instancia (la heredó un proceso de la
     * misma instancia).
     */
    private static function comprobarEntorno(string $carpeta): void
    {
        $propias = null;
        foreach (self::AJENAS as $variable) {
            $valor = self::leer($variable);
            if ($valor === null) {
                continue;
            }
            $propias ??= Dotenv::parse((string) file_get_contents($carpeta.'/.env'));
            if (($propias[$variable] ?? null) !== $valor) {
                throw new RuntimeException('El entorno trae otro '.$variable.': una instancia solo arranca con el de su .env.');
            }
        }
    }

    private static function leer(string $variable): ?string
    {
        $valor = $_SERVER[$variable] ?? $_ENV[$variable] ?? getenv($variable);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
