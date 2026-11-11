<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion;

/**
 * Marca que el código en ejecución es la sincronización, la única que puede escribir en las
 * filas sincronizadas.
 */
final class ContextoSincronizacion
{
    private static int $nivel = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public static function ejecutar(callable $accion): mixed
    {
        self::$nivel++;
        try {
            return $accion();
        } finally {
            self::$nivel--;
        }
    }

    public static function activo(): bool
    {
        return self::$nivel > 0;
    }
}
