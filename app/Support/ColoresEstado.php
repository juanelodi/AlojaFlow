<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Los colores del calendario que elige cada usuario (D-090). La letra se elige sola para que se lea: blanco o tinta, la que contraste más.
 */
final class ColoresEstado
{
    /** Como se guarda (y se traduce) => a qué barras del calendario se aplica. */
    public const ESTADOS = [
        'confirmada' => '.af-barra--confirmada',
        'sin_confirmar' => '.af-barra--sin-confirmar',
        'en_casa' => '.af-barra--en-casa',
        'salio' => '.af-barra--salio',
        // Al colorear por pago (D-154): también la leyenda. Más específicas que las de alojaflow.css, que van sin «html».
        'pago_total' => 'html[data-af-cal-colores="pago"] .af-pago--total',
        'pago_anticipo' => 'html[data-af-cal-colores="pago"] .af-pago--anticipo',
        'pago_sin_anticipo' => 'html[data-af-cal-colores="pago"] .af-pago--sin-anticipo',
        'pago_sin_confirmar' => 'html[data-af-cal-colores="pago"] .af-pago--sin-confirmar',
    ];

    /**
     * Las reglas de estilo de ese usuario. Solo pasa lo que es un color: esto se escribe en la página.
     */
    public static function css(?User $usuario): string
    {
        $reglas = '';
        foreach ((array) $usuario?->colores_estado as $estado => $color) {
            if (isset(self::ESTADOS[$estado]) && is_string($color) && preg_match('/^#[0-9a-fA-F]{6}$/', $color) === 1) {
                $reglas .= self::ESTADOS[$estado].'{background:'.$color.';color:'.self::letraPara($color).'}';
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
