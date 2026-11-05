<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use App\Paises\CatalogoPaises;

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

    /**
     * El número con su prefijo internacional, para WhatsApp. Si ya lo trae se respeta; si no, se le pone el del país de la propiedad.
     */
    public static function internacional(?string $telefono, ?string $pais): ?string
    {
        $normalizado = self::telefono((string) $telefono);
        if (str_starts_with($normalizado, '+')) {
            return strlen($normalizado) > 7 ? $normalizado : null;
        }
        $prefijo = $pais === null ? null : CatalogoPaises::prefijoTelefonico($pais);
        // Los prefijos viejos de larga distancia en México: 044, 045 y 01.
        if ($prefijo === '52') {
            $normalizado = (string) preg_replace('/^(044|045|01)(\d{10})$/', '$2', $normalizado);
        }
        $normalizado = ltrim($normalizado, '0');
        if ($prefijo === null || strlen($normalizado) < 6) {
            return null;
        }
        // Escrito con el prefijo, pero sin «+».
        if (str_starts_with($normalizado, $prefijo) && strlen($normalizado) >= strlen($prefijo) + 9) {
            return '+'.$normalizado;
        }

        return '+'.$prefijo.$normalizado;
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
