<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Los colores del calendario que elige cada usuario (D-090). La letra se elige sola para que se lea: blanco o tinta, la que contraste más.
 */
final class ColoresEstado
{
    /** Como se guarda (y se traduce) => su clase en el calendario. */
    public const ESTADOS = ['confirmada' => 'confirmada', 'sin_confirmar' => 'sin-confirmar', 'en_casa' => 'en-casa', 'salio' => 'salio'];

    /**
     * Las reglas de estilo de ese usuario. Solo pasa lo que es un color: esto se escribe en la página.
     */
    public static function css(?User $usuario): string
    {
        $reglas = '';
        foreach ((array) $usuario?->colores_estado as $estado => $color) {
            if (isset(self::ESTADOS[$estado]) && is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
                $reglas .= '.af-barra--'.self::ESTADOS[$estado].'{background:'.$color.';color:'.self::letraPara($color).'}';
            }
        }

        return $reglas;
    }

    public static function letraPara(string $fondo): string
    {
        return self::contraste($fondo, '#ffffff') >= self::contraste($fondo, '#0b1f2a') ? '#ffffff' : '#0b1f2a';
    }

    private static function contraste(string $a, string $b): float
    {
        $la = self::luminancia($a);
        $lb = self::luminancia($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function luminancia(string $color): float
    {
        $canal = function (int $inicio) use ($color): float {
            $valor = hexdec(substr($color, $inicio, 2)) / 255;

            return $valor <= 0.03928 ? $valor / 12.92 : (($valor + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $canal(1) + 0.7152 * $canal(3) + 0.0722 * $canal(5);
    }
}
