<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

/**
 * Quita de un error lo que podría ser un dato personal antes de guardarlo o mandarlo: correos, teléfonos,
 * números de tarjeta o documento, lo que va entre comillas y las rutas del servidor. El receptor vuelve a
 * limpiar lo mismo, por si una versión deja pasar algo.
 */
final class Saneador
{
    /** Carpetas de la aplicación: desde ahí, una ruta no dice nada de quién es ni dónde está. */
    private const RAICES = ['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'storage', 'vendor', 'public', 'instalador'];

    public static function texto(?string $texto, int $largo = 2000): string
    {
        if ($texto === null) {
            return '';
        }
        $texto = self::sinControl($texto);
        $texto = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[correo]', $texto) ?? '';
        $texto = self::rutasEnTexto($texto);
        // Entre comillas dobles, PHP pone también nombres de código («Undefined array key "tarifa"»): esos se quedan.
        $texto = preg_replace_callback(
            '/(\b(?:key|[Cc]lass|[Ii]nterface|[Tt]rait|[Ee]num|property|constant|method|function|column|table|index|route|view|component)\s)?"([^"\n]{1,500})"/u',
            fn (array $c): string => $c[1] !== '' && preg_match('/^[A-Za-z_][A-Za-z0-9_.\\\\:\-]{0,120}$/', $c[2]) === 1 ? $c[0] : $c[1].'"…"',
            $texto,
        ) ?? '';
        // Entre comillas simples van los valores de SQL.
        $texto = preg_replace("/'[^'\\n]{1,500}'/u", "'…'", $texto) ?? '';
        $texto = preg_replace('/(?<![\w.])\+?\d(?:[\d\s\-]{4,}\d)(?![\w.])/u', '[número]', $texto) ?? '';

        return mb_substr(trim($texto), 0, $largo);
    }

    /**
     * Un mensaje sin sus datos, para juntar los repetidos.
     */
    public static function plantilla(string $mensaje): string
    {
        $plantilla = preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', '{uuid}', $mensaje) ?? '';

        return mb_substr(preg_replace('/\d+(?:[.,]\d+)*/', '{n}', $plantilla) ?? '', 0, 500);
    }

    /**
     * La ruta desde la carpeta de la aplicación («app/Reservas/GestorReservas.php»).
     */
    public static function ruta(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }
        $ruta = str_replace('\\', '/', self::sinControl($ruta));
        $base = str_replace('\\', '/', base_path()).'/';
        if (str_starts_with($ruta, $base)) {
            return mb_substr(substr($ruta, strlen($base)), 0, 255);
        }
        $partes = array_values(array_filter(explode('/', $ruta), fn (string $parte): bool => $parte !== ''));
        foreach ($partes as $i => $parte) {
            if (in_array($parte, self::RAICES, true)) {
                return mb_substr(implode('/', array_slice($partes, $i)), 0, 255);
            }
        }

        return mb_substr((string) end($partes), 0, 255) ?: null;
    }

    public static function corto(?string $texto, int $largo = 255): ?string
    {
        if ($texto === null) {
            return null;
        }
        $texto = trim(self::sinControl($texto));

        return $texto === '' ? null : mb_substr($texto, 0, $largo);
    }

    private static function rutasEnTexto(string $texto): string
    {
        return preg_replace_callback(
            '#(?:[A-Za-z]:[\\\\/]|/)(?:[^\s:\'"()]+[\\\\/])+[^\s:\'"()]*#',
            fn (array $coincide): string => (string) self::ruta($coincide[0]),
            $texto,
        ) ?? '';
    }

    private static function sinControl(string $texto): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', mb_scrub($texto, 'UTF-8')) ?? '';
    }
}
