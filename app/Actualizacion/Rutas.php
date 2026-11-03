<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

/**
 * Qué rutas puede traer un paquete y cuáles no toca nunca una actualización: la configuración,
 * los datos y los archivos de cada instalación.
 */
final class Rutas
{
    /**
     * Prefijos que una actualización nunca escribe ni borra (sin distinguir mayúsculas: en
     * Windows «Storage/» es «storage/»).
     */
    public const PROTEGIDAS = [
        '.git/', '.herramientas/', '.claude/', 'storage/', 'bootstrap/cache/', 'public/storage',
        'public/hot', 'node_modules/', 'dist/',
    ];

    public static function valida(string $ruta): bool
    {
        if ($ruta === '' || strlen($ruta) > 240 || ! preg_match('#^[A-Za-z0-9_.@+~,=\-/]+$#', $ruta) || str_starts_with($ruta, '/')) {
            return false;
        }
        foreach (explode('/', $ruta) as $segmento) {
            // En Windows «carpeta.» es «carpeta»: ningún segmento puede acabar en punto.
            if ($segmento === '' || $segmento === '.' || $segmento === '..' || str_ends_with($segmento, '.')) {
                return false;
            }
        }

        return true;
    }

    public static function protegida(string $ruta): bool
    {
        $minusculas = strtolower($ruta);
        if ($minusculas === '.env' || (str_starts_with($minusculas, '.env.') && $minusculas !== '.env.example')) {
            return true;
        }
        foreach (self::PROTEGIDAS as $prefijo) {
            if (str_starts_with($minusculas, $prefijo)) {
                return true;
            }
        }

        return false;
    }
}
