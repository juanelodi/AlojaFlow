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
    public static function enUtf8(string $texto): string
    {
        $texto = (string) preg_replace('/^\xEF\xBB\xBF/', '', $texto);

        return mb_check_encoding($texto, 'UTF-8') ? $texto : (string) mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
    }

    public static function separador(string $primeraLinea): string
    {
        foreach (["\t", ';'] as $separador) {
            if (str_contains($primeraLinea, $separador)) {
                return $separador;
            }
        }

        return ',';
    }

    /**
     * Lo contrario de fila(): la comilla que se le puso al exportar no es parte del dato.
     */
    public static function sinProteccion(string $celda): string
    {
        return strlen($celda) > 1 && $celda[0] === "'" && in_array($celda[1], ['=', '+', '-', '@'], true) ? substr($celda, 1) : $celda;
    }

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
