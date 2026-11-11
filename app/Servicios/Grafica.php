<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Servicios;

/**
 * Lo que dibuja una gráfica de barras: el navegador la pinta sin JavaScript y sale igual al imprimir. Dos monedas no se comparan en una
 * misma escala, así que con más de una no hay gráfica: queda la tabla.
 *
 * @phpstan-type DatosGrafica array{series: list<string>, maximo: float, filas: list<array{etiqueta: string, valores: list<array{0: float, 1: string}>}>}
 */
final class Grafica
{
    private const FILAS_MAXIMAS = 31;

    /**
     * @template T
     *
     * @param  list<string>  $series  el nombre de cada serie; una sola no lleva leyenda
     * @param  iterable<T>  $datos
     * @param  callable(T): string  $etiqueta
     * @param  callable(T): list<array{0: int|float, 1: string}>  $valores  valor y texto de cada serie
     * @param  (callable(T): string)|null  $moneda
     * @return DatosGrafica|null
     */
    public static function de(array $series, iterable $datos, callable $etiqueta, callable $valores, ?callable $moneda = null): ?array
    {
        $filas = [];
        $monedas = [];
        $maximo = 0.0;
        foreach ($datos as $fila) {
            if ($moneda !== null) {
                $monedas[$moneda($fila)] = true;
            }
            $lista = [];
            foreach ($valores($fila) as [$valor, $texto]) {
                $maximo = max($maximo, abs((float) $valor));
                $lista[] = [(float) $valor, $texto];
            }
            $filas[] = ['etiqueta' => $etiqueta($fila), 'valores' => $lista];
        }
        if ($filas === [] || $maximo <= 0 || count($monedas) > 1) {
            return null;
        }

        return ['series' => $series, 'maximo' => $maximo, 'filas' => array_slice($filas, 0, self::FILAS_MAXIMAS)];
    }
}
