<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use Illuminate\Support\Facades\DB;

/**
 * Copia SQL (comprimida) de la base de datos antes de actualizar, sin depender de mysqldump, que
 * no suele haber en un hosting compartido. Se restaura con: gunzip -c copia.sql.gz | mysql …
 */
class CopiaBaseDatos
{
    /** Tablas de las que solo se copia la estructura. */
    // hd_filas se vuelve a llenar en la siguiente sincronización: guardarla solo engorda la copia.
    private const SIN_DATOS = ['cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'hd_filas'];

    private const FILAS_POR_INSERT = 200;

    /**
     * @return int tablas copiadas
     */
    public function volcar(string $ruta, string $version): int
    {
        $salida = gzopen($ruta, 'wb6');
        if ($salida === false) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $ruta]));
        }

        $pdo = DB::connection()->getPdo();
        gzwrite($salida, "-- Copia de la base de datos de AlojaFlow $version, ".now()->toIso8601String()."\n");
        gzwrite($salida, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

        $tablas = 0;
        foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $fila) {
            $tabla = (string) array_values((array) $fila)[0];
            $nombre = '`'.str_replace('`', '``', $tabla).'`';
            $creacion = (array) DB::selectOne('SHOW CREATE TABLE '.$nombre);
            gzwrite($salida, "DROP TABLE IF EXISTS $nombre;\n".array_values($creacion)[1].";\n\n");
            $tablas++;

            if (in_array($tabla, self::SIN_DATOS, true)) {
                continue;
            }
            // Las columnas generadas (noches, pendiente…) no se insertan: las calcula MySQL.
            $columnas = [];
            foreach (DB::select('SHOW COLUMNS FROM '.$nombre) as $columna) {
                $columna = (array) $columna;
                if (! str_contains(strtoupper((string) ($columna['Extra'] ?? '')), 'GENERATED')) {
                    $columnas[] = (string) $columna['Field'];
                }
            }
            $lista = implode(',', array_map(fn (string $c): string => '`'.str_replace('`', '``', $c).'`', $columnas));
            $lote = [];
            foreach (DB::table($tabla)->select($columnas)->cursor() as $registro) {
                $valores = array_map(
                    fn (mixed $v): string => $v === null ? 'NULL' : $pdo->quote((string) $v),
                    array_values((array) $registro),
                );
                $lote[] = '('.implode(',', $valores).')';
                if (count($lote) >= self::FILAS_POR_INSERT) {
                    gzwrite($salida, "INSERT INTO $nombre ($lista) VALUES\n".implode(",\n", $lote).";\n");
                    $lote = [];
                }
            }
            if ($lote !== []) {
                gzwrite($salida, "INSERT INTO $nombre ($lista) VALUES\n".implode(",\n", $lote).";\n");
            }
            gzwrite($salida, "\n");
        }

        gzwrite($salida, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($salida);

        return $tablas;
    }
}
