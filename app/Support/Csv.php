<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Filas para exportar a CSV. Un huésped escribe su nombre en una página pública: si empieza con «=»,
 * Excel lo ejecutaría como fórmula al abrir el archivo el contador.
 */
final class Csv
{
    /** Lo que dice ser una descarga para Excel. */
    public const TIPO_EXCEL = 'text/csv; charset=UTF-16LE';

    private const MARCA_UTF16 = "\xFF\xFE";

    public static function enUtf8(string $texto): string
    {
        if (str_starts_with($texto, self::MARCA_UTF16)) {
            return (string) mb_convert_encoding(substr($texto, 2), 'UTF-8', 'UTF-16LE');
        }
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
     * Año-mes-día, o como la guarda Excel en el país de la propiedad: 03/04/2027 no se adivina.
     */
    public static function fecha(string $texto, bool $mesPrimero = false): ?string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $texto, $m) === 1) {
            [$anio, $mes, $dia] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $texto, $m) === 1) {
            [$dia, $mes, $anio] = $mesPrimero ? [(int) $m[2], (int) $m[1], (int) $m[3]] : [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($mes, $dia, $anio) ? sprintf('%04d-%02d-%02d', $anio, $mes, $dia) : null;
    }

    public static function formatoFecha(bool $mesPrimero): string
    {
        return __($mesPrimero ? 'importar.formato_mes_primero' : 'importar.formato_dia_primero');
    }

    /**
     * Es encabezado si donde van las dos fechas no hay ni una cifra. Una fecha que no se entiende no se salta callada: esa línea dice su error.
     *
     * @param  array<int, string|null>  $campos
     */
    public static function encabezadoDeFechas(array $campos): bool
    {
        foreach ([1, 2] as $columna) {
            $celda = (string) ($campos[$columna] ?? '');
            if ($celda === '' || preg_match('/\d/', $celda) === 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Acepta coma o punto decimal: el último separador es el de los decimales.
     */
    public static function importe(string $texto, string $moneda): ?int
    {
        $limpio = (string) preg_replace('/[^\d.,]/', '', $texto);
        if (str_contains($texto, '-') || preg_match('/\d/', $limpio) !== 1) {
            return null;
        }
        $ultimo = max((int) strrpos($limpio, ','), (int) strrpos($limpio, '.'));
        // Sin decimales después del último separador de tres cifras, es de miles: 1,200 son mil doscientos.
        $tieneDecimales = (strrpos($limpio, ',') !== false || strrpos($limpio, '.') !== false) && strlen($limpio) - $ultimo - 1 !== 3;
        $entero = $tieneDecimales ? substr($limpio, 0, $ultimo) : $limpio;
        $decimales = $tieneDecimales ? substr($limpio, $ultimo + 1) : '';
        $numero = str_replace([',', '.'], '', $entero).($decimales === '' ? '' : '.'.$decimales);

        return is_numeric($numero) ? Dinero::aUnidadMenor($numero, $moneda) : null;
    }

    /**
     * Lo contrario de fila(): la comilla que se le puso al exportar no es parte del dato.
     */
    public static function sinProteccion(string $celda): string
    {
        return strlen($celda) > 1 && $celda[0] === "'" && in_array($celda[1], ['=', '+', '-', '@'], true) ? substr($celda, 1) : $celda;
    }

    /**
     * Un archivo que Excel abre en columnas en cualquier país: con comas, donde la lista se separa con «;» saldría todo en una sola, y con «;», donde
     * se separa con comas. Con tabuladores y en UTF-16 no depende de eso (D-128).
     *
     * @param  iterable<array<int, mixed>>  $filas
     */
    public static function paraExcel(iterable $filas): string
    {
        return self::MARCA_UTF16.implode('', iterator_to_array(self::lineasParaExcel($filas), false));
    }

    /**
     * Lo que puede ser mucho llega como generador, para no cargar todos los modelos de una vez.
     *
     * @param  iterable<array<int, mixed>>  $filas
     */
    public static function descargaParaExcel(iterable $filas, string $nombre): StreamedResponse
    {
        return response()->streamDownload(function () use ($filas): void {
            echo self::MARCA_UTF16;
            foreach (self::lineasParaExcel($filas) as $linea) {
                echo $linea;
            }
        }, $nombre, ['Content-Type' => self::TIPO_EXCEL]);
    }

    /**
     * Un número con punto decimal, para sumarlo en Excel. Excel lo lee con el separador de su país, y 1000.50 no es un número en el de España.
     */
    public static function numero(string $decimal, string $moneda): string
    {
        return str_replace('.', Dinero::separadorDecimal($moneda), $decimal);
    }

    /**
     * @param  iterable<array<int, mixed>>  $filas
     * @return Generator<int, string>
     */
    private static function lineasParaExcel(iterable $filas): Generator
    {
        $flujo = fopen('php://memory', 'r+b');
        if ($flujo === false) {
            return;
        }
        foreach ($filas as $fila) {
            rewind($flujo);
            ftruncate($flujo, 0);
            fputcsv($flujo, self::fila($fila), "\t", '"', '', "\r\n");
            rewind($flujo);
            yield (string) mb_convert_encoding((string) stream_get_contents($flujo), 'UTF-16LE', 'UTF-8');
        }
        fclose($flujo);
    }

    /**
     * @param  array<int, mixed>  $fila
     * @return list<string>
     */
    public static function fila(array $fila): array
    {
        return array_map(function (mixed $celda): string {
            $texto = (string) $celda;
            // Un importe negativo con coma decimal es un número, no una fórmula.
            if ($texto !== '' && ! is_numeric(str_replace(',', '.', $texto)) && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$texto;
            }

            return $texto;
        }, array_values($fila));
    }
}
