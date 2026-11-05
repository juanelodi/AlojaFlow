<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Publico;

/**
 * Un texto escrito en varios idiomas por el alojamiento.
 */
final class Textos
{
    /**
     * El del idioma pedido; si no, el de la propiedad; si no, el que haya. Mejor en otro idioma que nada.
     *
     * @param  array<array-key, mixed>  $porIdioma
     */
    public static function elegir(array $porIdioma, string $idioma, string $principal): ?string
    {
        foreach ([$idioma, $principal, ...array_keys($porIdioma)] as $clave) {
            $texto = $porIdioma[$clave] ?? null;
            if (is_string($texto) && trim($texto) !== '') {
                return trim($texto);
            }
        }

        return null;
    }
}
