<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

/**
 * Filas para exportar a CSV. Un huésped escribe su nombre en una página pública: si empieza con «=»,
 * Excel lo ejecutaría como fórmula al abrir el archivo el contador.
 */
final class Csv
{
    /**
     * @param  array<int, mixed>  $fila
     * @return list<string>
     */
    public static function fila(array $fila): array
    {
        return array_map(function (mixed $celda): string {
            $texto = (string) $celda;
            if ($texto !== '' && ! is_numeric($texto) && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$texto;
            }

            return $texto;
        }, array_values($fila));
    }
}
