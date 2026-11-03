<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Lee una copia de HotelDruid línea a línea, sin cargarla entera y sin ejecutar nada
 * (regla 11; subordinacion.md parte B). El formato parece XML pero no lo es.
 *
 * Lógica del formato tomada de HotelDruid (crea_backup.php e includes/funzioni_backup.php,
 * © Marco M. F. De Santis, AGPL v3), reescrita: ver THIRD-PARTY-NOTICES.md.
 */
class LectorCopia
{
    private const CABECERA = '<?php exit(); ?>';

    private const BUSCAR = ['</righetabella@%&@>', '</riga@%&@>', '<riga@%&@>', '</cmp@%&@>', '@%&@@%&@'];

    private const REEMPLAZAR = ['</righetabella>', '</riga>', '<riga>', '</cmp>', '@%&@'];

    /**
     * @param  callable(string, int|null, array<string, string|null>): void  $alFila  tabla base, año y fila
     */
    public function leer(string $ruta, callable $alFila): ResumenCopia
    {
        $archivo = fopen($ruta, 'rb');
        if ($archivo === false) {
            throw $this->formato('no se pudo abrir la copia');
        }

        $maxLinea = (int) config('alojaflow.subordinacion.tamano_maximo_linea');
        $estado = 'cabecera';
        $version = null;
        $fechaCopia = null;
        $idioma = null;
        $inicioSesion = false;
        $finDatabase = false;
        $finBackup = false;
        $nombreArchivo = '';
        $contenidoArchivo = '';
        $tabla = '';
        $base = '';
        $anio = null;
        $conservar = false;
        $columnas = [];
        $fila = null;
        $filas = [];
        $tablasVistas = [];
        $anios = [];
        $incidencias = [];
        $numLinea = 0;

        try {
            while (($bruta = fgets($archivo, $maxLinea + 2)) !== false) {
                $numLinea++;
                if (strlen($bruta) > $maxLinea && ! str_ends_with($bruta, "\n")) {
                    throw $this->formato("línea $numLinea demasiado larga");
                }
                $linea = rtrim($bruta, "\r\n");

                if ($numLinea === 1) {
                    if ($linea !== self::CABECERA) {
                        throw $this->formato('falta la cabecera <?php exit(); ?>');
                    }

                    continue;
                }

                switch ($estado) {
                    case 'cabecera':
                        if ($fechaCopia === null && preg_match('/^<!--\s+(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\s+-->$/', $linea, $m)) {
                            $fechaCopia = $m[1];
                        } elseif ($linea === '<backup>') {
                            $estado = 'backup';
                        }
                        break;

                    case 'backup':
                        if (preg_match('#^<versione>(.*)</versione>$#', $linea, $m)) {
                            $version = $m[1];
                            if (! in_array($version, Esquema::VERSIONES_ADMITIDAS, true)) {
                                throw new ErrorSincronizacion(CodigoError::VersionNoAdmitida, 'leer', __('sincronizacion.version_no_admitida', ['version' => $version]));
                            }
                        } elseif ($linea === '<file>') {
                            $estado = 'archivo';
                            $nombreArchivo = '';
                        } elseif ($linea === '<database>') {
                            if ($version === null) {
                                throw $this->formato('falta <versione>');
                            }
                            $estado = 'database';
                        } elseif ($linea === '</backup>') {
                            $finBackup = true;
                            $estado = 'fin';
                        }
                        break;

                    case 'archivo':
                        if (preg_match('#^<nomefile>(.*)</nomefile>$#', $linea, $m)) {
                            $nombreArchivo = basename($m[1]);
                            if ($nombreArchivo === 'abilita_login') {
                                $inicioSesion = true;
                            }
                        } elseif ($linea === '<contenuto>') {
                            $estado = 'contenido';
                            $contenidoArchivo = '';
                        } elseif ($linea === '</file>') {
                            $estado = 'backup';
                        }
                        break;

                    case 'contenido':
                        // Solo se mira lingua.php y nunca se ejecuta: se busca un patrón fijo (B6).
                        $fin = str_ends_with($linea, '</contenuto>');
                        if ($nombreArchivo === 'lingua.php' && strlen($contenidoArchivo) < 4096) {
                            $contenidoArchivo .= ($fin ? substr($linea, 0, -strlen('</contenuto>')) : $linea)."\n";
                        }
                        if ($fin) {
                            if ($nombreArchivo === 'lingua.php' && preg_match('/\$lingua\[1\]\s*=\s*"([a-z]{2,3})(?:-[a-z]+)?"/i', $contenidoArchivo, $m)) {
                                $idioma = strtolower($m[1]);
                            }
                            $estado = 'archivo';
                        }
                        break;

                    case 'database':
                        if ($linea === '<tabella>') {
                            $estado = 'tabla';
                            $tabla = '';
                            $columnas = [];
                        } elseif ($linea === '</database>') {
                            $finDatabase = true;
                            $estado = 'backup';
                        }
                        break;

                    case 'tabla':
                        if (preg_match('#^<nometabella>(.*)</nometabella>$#', $linea, $m)) {
                            $tabla = $m[1];
                            if (! preg_match('/^[a-z]+([0-9]{4})?$/', $tabla)) {
                                throw $this->formato("nombre de tabla inválido en la línea $numLinea");
                            }
                            [$base, $anio] = Esquema::separar($tabla);
                            $conservar = Esquema::seConserva($base, $anio);
                            $tablasVistas[$tabla] = true;
                            $filas[$tabla] = 0;
                        } elseif (preg_match('#^<nomecolonna>(.*)</nomecolonna>$#', $linea, $m)) {
                            if (! preg_match('/^[a-z0-9_]+$/', $m[1])) {
                                throw $this->formato("nombre de columna inválido en la línea $numLinea");
                            }
                            $columnas[] = $m[1];
                        } elseif ($linea === '<righetabella>') {
                            if ($tabla === '' || $columnas === []) {
                                throw $this->formato("tabla sin nombre o sin columnas en la línea $numLinea");
                            }
                            $estado = 'filas';
                        }
                        break;

                    case 'filas':
                        if ($fila === null) {
                            if ($linea === '</righetabella>') {
                                $estado = 'fin_tabla';
                                break;
                            }
                            if (! str_starts_with($linea, '<riga>')) {
                                break;
                            }
                            $fila = $linea;
                        } else {
                            $fila .= "\n".$linea;
                        }

                        if (str_ends_with($linea, '</riga>')) {
                            if ($conservar || $base === 'anni') {
                                $valores = $this->decodificarFila($fila, count($columnas), $numLinea, $incidencias);
                                $asociativa = array_combine($columnas, $valores);
                                if ($base === 'anni' && $asociativa['idanni'] !== null) {
                                    $anios[] = (int) $asociativa['idanni'];
                                }
                                if ($conservar) {
                                    $alFila($base, $anio, $asociativa);
                                    $filas[$tabla]++;
                                }
                            }
                            $fila = null;
                        }
                        break;

                    case 'fin_tabla':
                        if ($linea === '</tabella>') {
                            $estado = 'database';
                        }
                        break;
                }
            }
        } finally {
            fclose($archivo);
        }

        if ($version === null || ! $finDatabase || ! $finBackup) {
            throw new ErrorSincronizacion(CodigoError::CopiaIncompleta, 'leer', __('sincronizacion.copia_cortada'));
        }
        $this->comprobarTablas($tablasVistas, $anios);

        return new ResumenCopia(
            version: $version,
            fechaCopia: $fechaCopia,
            idioma: $idioma,
            inicioSesionActivado: $inicioSesion,
            filas: array_filter($filas, fn (int $n, string $t): bool => Esquema::seConserva(...Esquema::separar($t)), ARRAY_FILTER_USE_BOTH),
            anios: array_values(array_unique($anios)),
            incidencias: $incidencias,
        );
    }

    /**
     * Separa y decodifica los valores de una fila (subordinacion.md B3 y B4).
     *
     * @param  array<string, int>  $incidencias
     * @return list<string|null>
     */
    private function decodificarFila(string $fila, int $esperadas, int $numLinea, array &$incidencias): array
    {
        if (! str_starts_with($fila, '<riga><cmp>') || ! str_ends_with($fila, '</cmp></riga>')) {
            throw $this->formato("fila mal formada antes de la línea $numLinea");
        }

        $cuerpo = substr($fila, strlen('<riga><cmp>'), -strlen('</cmp></riga>'));
        $partes = explode('</cmp><cmp>', $cuerpo);
        if (count($partes) !== $esperadas) {
            throw $this->formato("la fila antes de la línea $numLinea tiene ".count($partes)." valores y se esperaban $esperadas");
        }

        $valores = [];
        foreach ($partes as $parte) {
            if ($parte === '') {
                $valores[] = null; // vacío = nulo, como en la restauración de HotelDruid
                continue;
            }
            $valor = str_replace("\r\n", "\n", str_replace(self::BUSCAR, self::REEMPLAZAR, $parte));
            if (! mb_check_encoding($valor, 'UTF-8')) {
                $valor = mb_scrub($valor, 'UTF-8');
                $incidencias['utf8_invalido'] = ($incidencias['utf8_invalido'] ?? 0) + 1;
            }
            $valores[] = $valor;
        }

        return $valores;
    }

    /**
     * Tablas obligatorias y coherencia de años (subordinacion.md B8).
     *
     * @param  array<string, bool>  $vistas
     * @param  list<int>  $anios
     */
    private function comprobarTablas(array $vistas, array $anios): void
    {
        foreach (Esquema::TABLAS_GLOBALES_OBLIGATORIAS as $tabla) {
            if (! isset($vistas[$tabla])) {
                throw new ErrorSincronizacion(CodigoError::CopiaIncompleta, 'leer', __('sincronizacion.falta_tabla', ['tabla' => $tabla]));
            }
        }
        foreach ($anios as $anio) {
            foreach (Esquema::TABLAS_ANUALES as $base) {
                if (! isset($vistas[$base.$anio])) {
                    throw new ErrorSincronizacion(CodigoError::CopiaIncompleta, 'leer', __('sincronizacion.falta_tabla', ['tabla' => $base.$anio]));
                }
            }
        }
        foreach (array_keys($vistas) as $tabla) {
            [, $anio] = Esquema::separar($tabla);
            if ($anio !== null && ! in_array($anio, $anios, true)) {
                throw $this->formato("la tabla $tabla es de un año que no está en anni");
            }
        }
    }

    private function formato(string $detalle): ErrorSincronizacion
    {
        return new ErrorSincronizacion(CodigoError::FormatoInvalido, 'leer', $detalle);
    }
}
