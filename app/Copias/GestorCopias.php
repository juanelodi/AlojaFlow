<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Copias;

use App\Actualizacion\CopiaBaseDatos;
use App\Instalacion\CachesArranque;
use App\Models\RegistroAuditoria;
use App\Support\Ajustes;
use FilesystemIterator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;
use ZipArchive;

/**
 * Copias de seguridad completas desde el panel (D-038): un zip con la base de datos, los archivos
 * subidos y una ficha. No lleva el .env: quien tenga la copia no debe tener también las llaves. Por
 * eso el panel permite descargar el .env aparte, para guardarlo en otro lugar.
 */
class GestorCopias
{
    public const CONSERVAR_POR_OMISION = 14;

    private const ARCHIVO_BASE = 'base-datos.sql.gz';

    private const ARCHIVO_FICHA = 'copia.json';

    private const CARPETA_ARCHIVOS = 'archivos';

    public function __construct(private readonly CopiaBaseDatos $copiaBaseDatos)
    {
    }

    public function carpeta(): string
    {
        return storage_path('app/private/copias');
    }

    /**
     * @param  string  $origen  manual, programada o antes_de_restaurar
     * @return array{nombre: string, tamano: int, tablas: int}
     */
    public function crear(string $origen = 'manual', ?string $protegida = null): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
        File::ensureDirectoryExists($this->carpeta(), 0700);
        $marca = now('UTC')->format('Ymd-His');
        $nombre = 'copia-'.$marca.'-'.bin2hex(random_bytes(3)).'.zip';
        $temporalSql = $this->carpeta().'/'.$nombre.'.sql.gz';
        $temporalZip = $this->carpeta().'/'.$nombre.'.tmp';

        try {
            $version = (string) config('alojaflow.version');
            $tablas = $this->copiaBaseDatos->volcar($temporalSql, $version);

            $zip = new ZipArchive();
            if ($zip->open($temporalZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new ErrorCopia(__('copias.error.no_escribe'));
            }
            $zip->addFile($temporalSql, self::ARCHIVO_BASE);
            $archivos = $this->anadirArchivos($zip);
            $zip->addFromString(self::ARCHIVO_FICHA, (string) json_encode([
                'producto' => 'AlojaFlow',
                'version' => $version,
                'creada_en' => now('UTC')->toIso8601String(),
                'origen' => $origen,
                'tablas' => $tablas,
                'archivos' => $archivos,
                'huella_clave' => $this->huellaClave(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            if (! $zip->close()) {
                throw new ErrorCopia(__('copias.error.no_escribe'));
            }
            File::move($temporalZip, $this->carpeta().'/'.$nombre);
            @chmod($this->carpeta().'/'.$nombre, 0600);
        } finally {
            File::delete([$temporalSql, $temporalZip]);
        }

        Ajustes::guardar('copias.ultima', ['nombre' => $nombre, 'en' => now('UTC')->toIso8601String(), 'origen' => $origen]);
        RegistroAuditoria::anotar('copia.crear', null, ['copia' => $nombre, 'origen' => $origen]);
        $tamano = (int) filesize($this->carpeta().'/'.$nombre);
        $this->borrarAntiguas($nombre, $protegida);

        return ['nombre' => $nombre, 'tamano' => $tamano, 'tablas' => $tablas];
    }

    /**
     * @return list<array{nombre: string, tamano: int, creada_en: Carbon|null, version: string, origen: string, tablas: int, archivos: int, misma_clave: bool, valida: bool}>
     */
    public function listar(): array
    {
        if (! is_dir($this->carpeta())) {
            return [];
        }
        $copias = [];
        foreach (File::glob($this->carpeta().'/*.zip') ?: [] as $ruta) {
            $copias[] = $this->leerFicha($ruta);
        }
        usort($copias, fn (array $a, array $b): int => ($b['creada_en']?->getTimestamp() ?? 0) <=> ($a['creada_en']?->getTimestamp() ?? 0));

        return $copias;
    }

    public function ruta(string $nombre): string
    {
        if (preg_match('/^[A-Za-z0-9._\-]+\.zip$/', $nombre) !== 1 || str_contains($nombre, '..') || ! is_file($this->carpeta().'/'.$nombre)) {
            throw new ErrorCopia(__('copias.error.no_existe'));
        }

        return $this->carpeta().'/'.$nombre;
    }

    public function eliminar(string $nombre): void
    {
        File::delete($this->ruta($nombre));
        RegistroAuditoria::anotar('copia.eliminar', null, ['copia' => $nombre]);
    }

    /**
     * Coloca en la carpeta de copias un zip subido, si de verdad es una copia de AlojaFlow.
     */
    public function importar(string $rutaSubida, string $nombreOriginal): string
    {
        $ficha = $this->leerFicha($rutaSubida);
        if (! $ficha['valida']) {
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
        File::ensureDirectoryExists($this->carpeta(), 0700);
        $base = preg_replace('/[^A-Za-z0-9._\-]/', '-', pathinfo($nombreOriginal, PATHINFO_FILENAME)) ?: 'copia';
        $nombre = substr($base, 0, 80).'-'.bin2hex(random_bytes(3)).'.zip';
        File::copy($rutaSubida, $this->carpeta().'/'.$nombre);
        @chmod($this->carpeta().'/'.$nombre, 0600);

        return $nombre;
    }

    /**
     * Sustituye los datos por los de la copia. Antes guarda el estado actual y, si la restauración
     * falla a medias, vuelve a él. Después migra: una copia vieja queda al día con esta versión.
     */
    public function restaurar(string $nombre): void
    {
        $ruta = $this->ruta($nombre);
        $ficha = $this->leerFicha($ruta);
        if (! $ficha['valida']) {
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
        if (version_compare($ficha['version'], (string) config('alojaflow.version'), '>')) {
            throw new ErrorCopia(__('copias.error.mas_nueva', ['version' => $ficha['version']]));
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(900);
        }

        // La que se va a restaurar puede ser la más antigua: al guardar esta, no debe borrarse.
        $seguridad = $this->crear('antes_de_restaurar', $nombre);
        try {
            $this->cargarDesdeZip($ruta);
        } catch (Throwable $e) {
            report($e);
            $this->cargarDesdeZip($this->ruta($seguridad['nombre']));
            throw new ErrorCopia(__('copias.error.restaurar', ['detalle' => $e->getMessage()]));
        }

        Ajustes::olvidar();
        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new ErrorCopia(__('copias.error.restaurar', ['detalle' => trim(Artisan::output())]));
        }
        Artisan::call('optimize:clear');
        CachesArranque::rehacer();
        RegistroAuditoria::anotar('copia.restaurar', null, ['copia' => $nombre, 'seguridad' => $seguridad['nombre']]);
    }

    /**
     * @param  string|null  $recien  la copia recién creada cuenta, pero nunca se borra: dos copias en
     *                               el mismo segundo no tienen un orden seguro
     * @param  string|null  $protegida  la que se está usando en este momento
     */
    public function borrarAntiguas(?string $recien = null, ?string $protegida = null): int
    {
        $conservar = max(1, (int) Ajustes::obtener('copias.conservar', self::CONSERVAR_POR_OMISION)) - ($recien === null ? 0 : 1);
        $otras = array_values(array_filter($this->listar(), fn (array $copia): bool => $copia['nombre'] !== $recien && $copia['nombre'] !== $protegida));
        $borradas = 0;
        foreach (array_slice($otras, $conservar) as $copia) {
            File::delete($this->carpeta().'/'.$copia['nombre']);
            $borradas++;
        }

        return $borradas;
    }

    /**
     * Huella de APP_KEY, para avisar si la copia viene de otra instalación: sus datos cifrados no
     * se podrían leer aquí.
     */
    private function huellaClave(): string
    {
        return hash('sha256', 'alojaflow-copia:'.(string) config('app.key'));
    }

    private function cargarDesdeZip(string $ruta): void
    {
        $zip = new ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
        $temporal = tempnam(sys_get_temp_dir(), 'af-copia');
        $entrada = $zip->getStream(self::ARCHIVO_BASE);
        $salida = $temporal === false ? false : fopen($temporal, 'wb');
        if ($entrada === false || $salida === false) {
            $zip->close();
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
        stream_copy_to_stream($entrada, $salida);
        fclose($entrada);
        fclose($salida);

        try {
            $this->ejecutarSql((string) $temporal);
            $this->restaurarArchivos($zip);
        } finally {
            $zip->close();
            File::delete((string) $temporal);
        }
    }

    /**
     * Cada sentencia termina en «;» al final de una línea: al volcar, los saltos de línea dentro de
     * los valores quedan escapados, así que un «;» al final de línea nunca es parte de un dato.
     */
    private function ejecutarSql(string $rutaGz): void
    {
        $entrada = gzopen($rutaGz, 'rb');
        if ($entrada === false) {
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
        $conexion = DB::connection();
        $conexion->unprepared('SET FOREIGN_KEY_CHECKS=0');
        try {
            // Lo que haya de más en la instalación actual (tablas nuevas) también debe irse.
            foreach ($conexion->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $fila) {
                $conexion->unprepared('DROP TABLE IF EXISTS `'.str_replace('`', '``', (string) array_values((array) $fila)[0]).'`');
            }
            $sentencia = '';
            while (($linea = gzgets($entrada)) !== false) {
                if ($sentencia === '' && (trim($linea) === '' || str_starts_with($linea, '-- '))) {
                    continue;
                }
                $sentencia .= $linea;
                if (str_ends_with(rtrim($linea), ';')) {
                    $this->comprobarSentencia($sentencia);
                    $conexion->unprepared($sentencia);
                    $sentencia = '';
                }
            }
        } finally {
            gzclose($entrada);
            $conexion->unprepared('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * Una copia se puede subir desde otro equipo: solo se ejecuta lo que escribe AlojaFlow al volcar
     * (crear tablas y meter filas), no cualquier SQL que traiga el archivo.
     */
    private function comprobarSentencia(string $sentencia): void
    {
        if (preg_match('/^\s*(SET (NAMES|FOREIGN_KEY_CHECKS)\b|DROP TABLE IF EXISTS `|CREATE TABLE `|INSERT INTO `[^`]+` \([^)]*\) VALUES\b)/', $sentencia) !== 1) {
            throw new ErrorCopia(__('copias.error.no_es_copia'));
        }
    }

    private function carpetaArchivos(): string
    {
        return storage_path('app/private/'.self::CARPETA_ARCHIVOS);
    }

    private function anadirArchivos(ZipArchive $zip): int
    {
        $carpeta = $this->carpetaArchivos();
        if (! is_dir($carpeta)) {
            return 0;
        }
        $cuantos = 0;
        $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta, FilesystemIterator::SKIP_DOTS));
        /** @var SplFileInfo $archivo */
        foreach ($iterador as $archivo) {
            if ($archivo->isFile()) {
                $relativa = str_replace('\\', '/', substr($archivo->getPathname(), strlen($carpeta) + 1));
                $zip->addFile($archivo->getPathname(), self::CARPETA_ARCHIVOS.'/'.$relativa);
                $cuantos++;
            }
        }

        return $cuantos;
    }

    private function restaurarArchivos(ZipArchive $zip): void
    {
        $carpeta = $this->carpetaArchivos();
        File::deleteDirectory($carpeta);
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nombre = (string) $zip->getNameIndex($i);
            if (! str_starts_with($nombre, self::CARPETA_ARCHIVOS.'/') || str_ends_with($nombre, '/') || str_contains($nombre, '..')) {
                continue;
            }
            $destino = storage_path('app/private/'.$nombre);
            File::ensureDirectoryExists(dirname($destino), 0700);
            $entrada = $zip->getStream($nombre);
            $salida = fopen($destino, 'wb');
            if ($entrada !== false && $salida !== false) {
                stream_copy_to_stream($entrada, $salida);
            }
            if (is_resource($entrada)) {
                fclose($entrada);
            }
            if (is_resource($salida)) {
                fclose($salida);
            }
        }
    }

    /**
     * @return array{nombre: string, tamano: int, creada_en: Carbon|null, version: string, origen: string, tablas: int, archivos: int, misma_clave: bool, valida: bool}
     */
    private function leerFicha(string $ruta): array
    {
        $ficha = null;
        $zip = new ZipArchive();
        if (@$zip->open($ruta) === true) {
            if ($zip->locateName(self::ARCHIVO_BASE) !== false) {
                $datos = json_decode((string) $zip->getFromName(self::ARCHIVO_FICHA), true);
                $ficha = is_array($datos) && ($datos['producto'] ?? null) === 'AlojaFlow' ? $datos : null;
            }
            $zip->close();
        }

        return [
            'nombre' => basename($ruta),
            'tamano' => (int) @filesize($ruta),
            'creada_en' => isset($ficha['creada_en']) ? Carbon::parse((string) $ficha['creada_en']) : null,
            'version' => (string) ($ficha['version'] ?? '—'),
            'origen' => (string) ($ficha['origen'] ?? 'manual'),
            'tablas' => (int) ($ficha['tablas'] ?? 0),
            'archivos' => (int) ($ficha['archivos'] ?? 0),
            'misma_clave' => ($ficha['huella_clave'] ?? null) === $this->huellaClave(),
            'valida' => $ficha !== null,
        ];
    }
}
