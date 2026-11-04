<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Forma comparable de un contacto: correos en minúsculas y teléfonos solo con dígitos y el «+».
 */
final class Contactos
{
    public static function normalizar(string $tipo, string $valor): string
    {
        $normalizado = $tipo === 'correo' ? mb_strtolower(trim($valor)) : self::telefono($valor);

        return mb_substr($normalizado, 0, 255);
    }

    private static function telefono(string $telefono): string
    {
        $limpio = (string) preg_replace('/[^\d+]/', '', $telefono);
        if (str_starts_with($limpio, '00')) {
            $limpio = '+'.substr($limpio, 2);
        }

        return str_starts_with($limpio, '+') ? '+'.str_replace('+', '', $limpio) : str_replace('+', '', $limpio);
    }
}
