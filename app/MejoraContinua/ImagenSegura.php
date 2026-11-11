<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Las imágenes de un reporte, antes de guardarlas para mandarlas (D-150): solo JPG o PNG por su contenido, sin
 * metadatos (la ubicación de una foto, el teléfono que la tomó) y de un tamaño razonable. El receptor vuelve a
 * revisarlas y a codificarlas.
 */
final class ImagenSegura
{
    private const PIXELES_MAXIMOS = 40_000_000;

    private const LADO_MAXIMO = 2560;

    /**
     * @param  string  $ruta  en el disco privado
     * @return bool si quedó una imagen válida; si no, se borra
     */
    public static function limpiar(string $ruta): bool
    {
        $disco = Storage::disk('local');
        try {
            $absoluta = $disco->path($ruta);
            $info = @getimagesize($absoluta);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absoluta);
            $tipo = $info === false ? null : $info[2];
            $valida = ($tipo === IMAGETYPE_JPEG && $mime === 'image/jpeg') || ($tipo === IMAGETYPE_PNG && $mime === 'image/png');
            if (! $valida || $info[0] < 1 || $info[1] < 1 || $info[0] * $info[1] > self::PIXELES_MAXIMOS) {
                $disco->delete($ruta);

                return false;
            }
            if (extension_loaded('gd')) {
                self::recodificar($absoluta, $tipo === IMAGETYPE_JPEG);
            }

            return true;
        } catch (Throwable) {
            $disco->delete($ruta);

            return false;
        }
    }

    /**
     * Vuelve a escribir solo los píxeles: así no se va nada escondido en el archivo.
     */
    private static function recodificar(string $ruta, bool $jpeg): void
    {
        $imagen = $jpeg ? @imagecreatefromjpeg($ruta) : @imagecreatefrompng($ruta);
        if (! $imagen instanceof GdImage) {
            throw new \RuntimeException('ilegible');
        }
        [$ancho, $alto] = [imagesx($imagen), imagesy($imagen)];
        if (max($ancho, $alto) > self::LADO_MAXIMO) {
            $escala = self::LADO_MAXIMO / max($ancho, $alto);
            $reducida = imagescale($imagen, max(1, (int) round($ancho * $escala)), max(1, (int) round($alto * $escala)), IMG_BICUBIC);
            $imagen = $reducida instanceof GdImage ? $reducida : $imagen;
        }
        if ($jpeg) {
            imagejpeg($imagen, $ruta, 85);

            return;
        }
        imagealphablending($imagen, false);
        imagesavealpha($imagen, true);
        imagepng($imagen, $ruta, 6);
    }
}
