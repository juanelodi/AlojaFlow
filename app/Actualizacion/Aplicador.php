<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Actualizacion;

use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

/**
 * Pone en la instalación los archivos de un paquete ya revisado, en dos pasos:
 *  1. extraer(): saca del zip a una carpeta aparte cada archivo nuevo o que cambia y comprueba su
 *     hash. Es lo lento y no toca la instalación.
 *  2. intercambiar(): mueve (rename) cada original a la copia y cada archivo nuevo a su sitio. Son
 *     segundos; cada paso se anota antes en un diario, así que se puede deshacer aunque el proceso
 *     muera a medias (deshacer()).
 */
class Aplicador
{
    public function extraer(Revision $revision, string $zipRuta, string $carpetaNuevos): void
    {
        $manifiesto = Manifiesto::desdeJson($revision->manifiestoJson);
        $zip = new ZipArchive();
        if ($zip->open($zipRuta, ZipArchive::RDONLY) !== true) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_es_zip'));
        }

        try {
            foreach (array_merge($revision->cambiados, $revision->nuevos) as $ruta) {
                $this->extraerArchivo($zip, $revision->base.'/'.$ruta, $carpetaNuevos.'/'.$ruta, $manifiesto->archivos[$ruta]);
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{sustituidos: int, creados: int, borrados: int}
     */
    public function intercambiar(Revision $revision, string $raiz, string $carpetaNuevos, string $carpetaAnterior, string $diario): array
    {
        $raiz = rtrim($raiz, '/\\');
        $anotaciones = @fopen($diario, 'ab');
        if ($anotaciones === false) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $diario]));
        }

        try {
            foreach ($revision->cambiados as $ruta) {
                $this->anotar($anotaciones, 'sustituye', $ruta);
                $this->mover($raiz.'/'.$ruta, $carpetaAnterior.'/'.$ruta);
                $this->mover($carpetaNuevos.'/'.$ruta, $raiz.'/'.$ruta);
            }
            foreach ($revision->nuevos as $ruta) {
                $this->anotar($anotaciones, 'crea', $ruta);
                $this->mover($carpetaNuevos.'/'.$ruta, $raiz.'/'.$ruta);
            }
            foreach ($revision->borrar as $ruta) {
                $this->anotar($anotaciones, 'borra', $ruta);
                $this->mover($raiz.'/'.$ruta, $carpetaAnterior.'/'.$ruta);
            }
        } catch (Throwable $e) {
            fclose($anotaciones);
            $this->deshacer($raiz, $carpetaAnterior, $diario);
            throw $e instanceof ErrorActualizacion ? $e : new ErrorActualizacion($e->getMessage(), 0, $e);
        }
        fclose($anotaciones);
        $this->limpiarOpcache();

        return ['sustituidos' => count($revision->cambiados), 'creados' => count($revision->nuevos), 'borrados' => count($revision->borrar)];
    }

    /**
     * Devuelve la instalación a como estaba según el diario, del último paso al primero. Se puede
     * repetir sin daño: cada paso mira lo que encuentra (sirve también tras un fallo al migrar).
     */
    public function deshacer(string $raiz, string $carpetaAnterior, string $diario): void
    {
        $raiz = rtrim($raiz, '/\\');
        $pasos = is_file($diario) ? (file($diario, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []) : [];

        foreach (array_reverse($pasos) as $paso) {
            [$accion, $ruta] = array_pad(explode("\t", $paso, 2), 2, '');
            if (! Rutas::valida($ruta) || Rutas::protegida($ruta)) {
                continue;
            }
            try {
                if ($accion === 'crea' && is_file($raiz.'/'.$ruta)) {
                    if (! @unlink($raiz.'/'.$ruta)) {
                        throw new ErrorActualizacion(__('actualizaciones.error.no_borra', ['ruta' => $ruta]));
                    }
                } elseif (($accion === 'sustituye' || $accion === 'borra') && is_file($carpetaAnterior.'/'.$ruta)) {
                    $this->mover($carpetaAnterior.'/'.$ruta, $raiz.'/'.$ruta);
                }
            } catch (Throwable $e) {
                Log::error('Actualización: no se pudo restaurar '.$ruta.': '.$e->getMessage());
            }
        }
        $this->limpiarOpcache();
    }

    private function extraerArchivo(ZipArchive $zip, string $entrada, string $destino, string $hash): void
    {
        $this->asegurarCarpeta(dirname($destino));
        $origen = $zip->getStream($entrada);
        $salida = @fopen($destino, 'wb');
        if ($origen === false || $salida === false) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $destino]));
        }
        $copiados = stream_copy_to_stream($origen, $salida);
        fclose($origen);
        fclose($salida);

        if ($copiados === false || ! hash_equals($hash, (string) hash_file('sha256', $destino))) {
            @unlink($destino);
            throw new ErrorActualizacion(__('actualizaciones.error.archivo_alterado', ['ruta' => $entrada]));
        }
    }

    /**
     * @param  resource  $anotaciones
     */
    private function anotar($anotaciones, string $accion, string $ruta): void
    {
        if (fwrite($anotaciones, $accion."\t".$ruta."\n") === false || ! fflush($anotaciones)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $ruta]));
        }
    }

    private function mover(string $origen, string $destino): void
    {
        $this->asegurarCarpeta(dirname($destino));
        if (! @rename($origen, $destino)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $destino]));
        }
    }

    private function asegurarCarpeta(string $carpeta): void
    {
        if (! is_dir($carpeta) && ! @mkdir($carpeta, 0755, true) && ! is_dir($carpeta)) {
            throw new ErrorActualizacion(__('actualizaciones.error.no_escribe', ['ruta' => $carpeta]));
        }
    }

    private function limpiarOpcache(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
}
