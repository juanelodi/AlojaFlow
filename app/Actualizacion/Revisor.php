<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Actualizacion;

use InvalidArgumentException;
use ZipArchive;

/**
 * Revisa un paquete de actualización sin cambiar nada: firma, versión, requisitos, integridad de
 * cada archivo y qué haría en la instalación. Lógica tomada del actualizador de AlojaFlow 1.2.x
 * (includes/funzioni_actualizar.php), reescrita.
 */
class Revisor
{
    public const ARCHIVO_MANIFIESTO = 'manifiesto.json';

    public const ARCHIVO_FIRMA = 'manifiesto.firma';

    /**
     * @param  array<string, string>|null  $instalado  archivos del manifiesto instalado (ruta => sha256), si se conoce
     */
    public function revisar(string $zipRuta, string $raiz, string $versionActual, string $clavePublica, ?array $instalado, bool $reinstalar = false): Revision
    {
        $revision = new Revision($versionActual, $reinstalar);
        $raiz = rtrim($raiz, '/\\');

        $this->requisitosGenerales($revision);
        if (! is_file($zipRuta)) {
            return $revision->error(__('actualizaciones.error.no_existe'));
        }
        if ((int) filesize($zipRuta) > (int) config('alojaflow.actualizaciones.tamano_maximo_paquete')) {
            return $revision->error(__('actualizaciones.error.demasiado_grande'));
        }

        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::RDONLY) !== true) {
            return $revision->error(__('actualizaciones.error.no_es_zip'));
        }

        try {
            $this->revisarZip($zip, $revision, $raiz, $clavePublica, $instalado);
        } finally {
            $zip->close();
        }

        return $revision;
    }

    /**
     * Si es un paquete de esa versión con la firma de quien publica, sin mirar nada más. Para lo que baja de
     * internet: lo demás (cada archivo, requisitos) lo revisa revisar() después.
     */
    public function firmadoPorQuienPublica(string $zipRuta, string $version, string $clavePublica): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            $base = Manifiesto::carpetaBase($version);
            $json = $zip->getFromName($base.'/'.self::ARCHIVO_MANIFIESTO);
            $firma = $zip->getFromName($base.'/'.self::ARCHIVO_FIRMA);

            return is_string($json) && is_string($firma) && Firma::verificar($json, $firma, $clavePublica)
                && Manifiesto::desdeJson($json)->version === $version;
        } catch (InvalidArgumentException) {
            return false;
        } finally {
            $zip->close();
        }
    }

    /**
     * @param  array<string, string>|null  $instalado
     */
    private function revisarZip(ZipArchive $zip, Revision $revision, string $raiz, string $clavePublica, ?array $instalado): void
    {
        if ($zip->numFiles > (int) config('alojaflow.actualizaciones.archivos_maximos') + 10) {
            $revision->error(__('actualizaciones.error.demasiados_archivos'));

            return;
        }

        // Una sola carpeta base «alojaflow-x.y.z/» con su manifiesto.
        $base = '';
        $entradas = [];
        $bytes = 0;
        $sistema = 0;
        $atributos = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $estado = $zip->statIndex($i);
            if ($estado === false) {
                $revision->error(__('actualizaciones.error.zip_danado'));

                return;
            }
            $nombre = (string) $estado['name'];
            if (preg_match('#^(alojaflow-\d{1,3}\.\d{1,3}\.\d{1,3})/'.self::ARCHIVO_MANIFIESTO.'$#', $nombre, $m)) {
                if ($base !== '') {
                    $revision->error(__('actualizaciones.error.varias_bases'));

                    return;
                }
                $base = $m[1];
            }
            if ($zip->getExternalAttributesIndex($i, $sistema, $atributos) && $sistema === ZipArchive::OPSYS_UNIX && (($atributos >> 16) & 0170000) === 0120000) {
                $revision->error(__('actualizaciones.error.enlace', ['ruta' => $nombre]));

                return;
            }
            if (! str_ends_with($nombre, '/')) {
                $entradas[$nombre] = true;
                $bytes += (int) $estado['size'];
            }
        }
        if ($base === '') {
            $revision->error(__('actualizaciones.error.sin_manifiesto'));

            return;
        }
        if ($bytes > (int) config('alojaflow.actualizaciones.tamano_maximo_descomprimido')) {
            $revision->error(__('actualizaciones.error.demasiado_grande'));

            return;
        }
        $revision->bytes = $bytes;
        $revision->base = $base;

        // Firma del manifiesto: sin ella no se mira nada más.
        $json = $zip->getFromName($base.'/'.self::ARCHIVO_MANIFIESTO);
        $firma = $zip->getFromName($base.'/'.self::ARCHIVO_FIRMA);
        if (! is_string($json) || ! is_string($firma) || ! Firma::verificar($json, $firma, $clavePublica)) {
            $revision->error(__('actualizaciones.error.firma'));

            return;
        }

        try {
            $manifiesto = Manifiesto::desdeJson($json);
        } catch (InvalidArgumentException $e) {
            $revision->error(__('actualizaciones.error.manifiesto', ['detalle' => $e->getMessage()]));

            return;
        }
        if (Manifiesto::carpetaBase($manifiesto->version) !== $base) {
            $revision->error(__('actualizaciones.error.manifiesto', ['detalle' => 'carpeta']));

            return;
        }
        $revision->version = $manifiesto->version;
        $revision->manifiestoJson = $json;

        $comparacion = version_compare($manifiesto->version, $revision->versionActual);
        if ($comparacion < 0) {
            $revision->error(__('actualizaciones.error.anterior', ['version' => $manifiesto->version, 'actual' => $revision->versionActual]));
        } elseif ($comparacion === 0 && ! $revision->reinstalar) {
            $revision->error(__('actualizaciones.error.misma', ['version' => $manifiesto->version]));
        }
        $this->requisitosPaquete($revision, $manifiesto);
        if ($revision->errores !== []) {
            return;
        }

        // Nada fuera del manifiesto y cada archivo idéntico a su hash.
        $propios = [$base.'/'.self::ARCHIVO_MANIFIESTO => true, $base.'/'.self::ARCHIVO_FIRMA => true];
        foreach (array_keys($entradas) as $nombre) {
            if (! isset($propios[$nombre]) && ! isset($manifiesto->archivos[substr($nombre, strlen($base) + 1)])) {
                $revision->error(__('actualizaciones.error.archivo_de_mas', ['ruta' => $nombre]));

                return;
            }
        }
        foreach ($manifiesto->archivos as $ruta => $hash) {
            $nombre = $base.'/'.$ruta;
            if (! isset($entradas[$nombre]) || ! hash_equals($hash, $this->hashEntrada($zip, $nombre))) {
                $revision->error(__('actualizaciones.error.archivo_alterado', ['ruta' => $ruta]));

                return;
            }
        }

        $this->compararConInstalacion($revision, $manifiesto, $raiz, $instalado);
        $notas = $zip->getFromName($base.'/CAMBIOS.md');
        $revision->notas = is_string($notas) ? $this->notasDeVersion($notas, $manifiesto->version) : null;
    }

    /**
     * Qué archivos son nuevos, cuáles cambian y qué obsoletos se borran o se conservan.
     *
     * @param  array<string, string>|null  $instalado
     */
    private function compararConInstalacion(Revision $revision, Manifiesto $manifiesto, string $raiz, ?array $instalado): void
    {
        $noEscribibles = [];
        foreach ($manifiesto->archivos as $ruta => $hash) {
            if (Rutas::protegida($ruta)) {
                continue; // configuración y datos de la instalación: una actualización no los toca
            }
            $destino = $raiz.'/'.$ruta;
            if (is_dir($destino)) {
                $revision->error(__('actualizaciones.error.es_carpeta', ['ruta' => $ruta]));

                return;
            }
            if (is_file($destino)) {
                $actual = (string) hash_file('sha256', $destino);
                if (hash_equals($hash, $actual)) {
                    $revision->iguales++;

                    continue;
                }
                $revision->cambiados[] = $ruta;
                if (isset($instalado[$ruta]) && ! hash_equals($instalado[$ruta], $actual)) {
                    $revision->modificadosLocalmente++;
                }
                if (! is_writable($destino)) {
                    $noEscribibles[] = $ruta;
                }
            } else {
                $revision->nuevos[] = $ruta;
                if (! $this->sePuedeCrear($destino)) {
                    $noEscribibles[] = $ruta;
                }
            }
        }

        foreach ($instalado ?? [] as $ruta => $hashAnterior) {
            if (isset($manifiesto->archivos[$ruta]) || ! Rutas::valida($ruta) || Rutas::protegida($ruta) || ! is_file($raiz.'/'.$ruta)) {
                continue;
            }
            // Solo se borra un obsoleto si sigue igual que en la versión instalada.
            if (hash_equals($hashAnterior, (string) hash_file('sha256', $raiz.'/'.$ruta))) {
                $revision->borrar[] = $ruta;
            } else {
                $revision->conservados[] = $ruta;
            }
        }

        if ($noEscribibles !== []) {
            $revision->error(__('actualizaciones.error.sin_permiso', ['cuantos' => count($noEscribibles), 'ejemplo' => $noEscribibles[0]]));
        }
        $libre = @disk_free_space($raiz);
        if (is_float($libre) && $libre < 2 * $revision->bytes) {
            $revision->error(__('actualizaciones.error.sin_espacio'));
        }
        if ($revision->modificadosLocalmente > 0) {
            $revision->avisos[] = __('actualizaciones.aviso.modificados', ['cuantos' => $revision->modificadosLocalmente]);
        }
        if ($revision->conservados !== []) {
            $revision->avisos[] = __('actualizaciones.aviso.conservados', ['cuantos' => count($revision->conservados)]);
        }
        if ($instalado === null) {
            $revision->avisos[] = __('actualizaciones.aviso.sin_manifiesto_instalado');
        }
    }

    private function requisitosGenerales(Revision $revision): void
    {
        foreach (['zip', 'sodium'] as $extension) {
            if (! extension_loaded($extension)) {
                $revision->error(__('actualizaciones.error.extension', ['extension' => $extension]));
            }
        }
    }

    private function requisitosPaquete(Revision $revision, Manifiesto $manifiesto): void
    {
        if (version_compare(PHP_VERSION, $manifiesto->phpMinimo, '<')) {
            $revision->error(__('actualizaciones.error.php', ['minimo' => $manifiesto->phpMinimo, 'actual' => PHP_VERSION]));
        }
        $extensiones = array_unique(array_merge((array) config('alojaflow.actualizaciones.extensiones'), $manifiesto->extensiones));
        foreach ($extensiones as $extension) {
            if (! extension_loaded((string) $extension)) {
                $revision->error(__('actualizaciones.error.extension', ['extension' => $extension]));
            }
        }
    }

    private function hashEntrada(ZipArchive $zip, string $nombre): string
    {
        $flujo = $zip->getStream($nombre);
        if ($flujo === false) {
            return '';
        }
        $contexto = hash_init('sha256');
        hash_update_stream($contexto, $flujo);
        fclose($flujo);

        return hash_final($contexto);
    }

    private function sePuedeCrear(string $destino): bool
    {
        $carpeta = dirname($destino);
        while (! is_dir($carpeta)) {
            $padre = dirname($carpeta);
            if ($padre === $carpeta) {
                return false;
            }
            $carpeta = $padre;
        }

        return is_writable($carpeta);
    }

    /**
     * La sección «## x.y.z» de CAMBIOS.md, hasta la siguiente versión.
     */
    private function notasDeVersion(string $cambios, string $version): ?string
    {
        if (! preg_match('/^## '.preg_quote($version, '/').'\b.*$/m', $cambios, $m, PREG_OFFSET_CAPTURE)) {
            return null;
        }
        $desde = (int) $m[0][1];
        $resto = substr($cambios, $desde + strlen($m[0][0]));
        $fin = preg_match('/^## /m', $resto, $n, PREG_OFFSET_CAPTURE) ? (int) $n[0][1] : strlen($resto);

        return trim($m[0][0]."\n".substr($resto, 0, $fin));
    }
}
