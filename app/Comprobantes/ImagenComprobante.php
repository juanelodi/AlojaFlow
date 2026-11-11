<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comprobantes;

use finfo;
use GdImage;

/**
 * Lo que suben desde una página pública se vuelve a dibujar (D-097): así no llega lo que traía escondido, ni la ubicación de la foto. El tipo se mira en el
 * contenido, no en el nombre, y los píxeles se cuentan antes de abrirla: una imagen chica de bytes puede ocupar toda la memoria al abrirse.
 */
final class ImagenComprobante
{
    public const BYTES_MAXIMOS = 5242880;

    private const PIXELES_MAXIMOS = 40000000;

    private const LADO_MAXIMO = 2000;

    private const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];

    /**
     * @return string la imagen en JPEG
     *
     * @throws ComprobanteRechazado
     */
    public static function limpiar(string $contenido): string
    {
        if (strlen($contenido) > self::BYTES_MAXIMOS) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_tamano'));
        }
        if (! in_array((new finfo(FILEINFO_MIME_TYPE))->buffer($contenido), self::TIPOS, true)) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_tipo'));
        }
        $medidas = @getimagesizefromstring($contenido);
        if ($medidas === false || $medidas[0] < 1 || $medidas[1] < 1 || $medidas[0] * $medidas[1] > self::PIXELES_MAXIMOS) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_imagen'));
        }
        if (! function_exists('imagecreatefromstring')) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_servidor'));
        }
        if (! self::cabeEnMemoria($medidas[0], $medidas[1])) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_memoria'));
        }
        $imagen = @imagecreatefromstring($contenido);
        if ($imagen === false) {
            throw new ComprobanteRechazado(__('grupos.comprobante.error_imagen'));
        }
        $imagen = self::enderezar($imagen, $contenido);
        $salida = self::enTamano($imagen);

        ob_start();
        imagejpeg($salida, null, 85);

        return (string) ob_get_clean();
    }

    /**
     * Abrir una imagen ocupa unos 5 bytes por píxel, y quedarse sin memoria no se puede atrapar: sería un error 500. Una foto de un teléfono actual
     * no cabe en los 128 MB de muchos hostings: si deja, se pide lo que falte.
     */
    private static function cabeEnMemoria(int $ancho, int $alto): bool
    {
        $limite = ini_parse_quantity((string) ini_get('memory_limit'));
        if ($limite <= 0) {
            return true;
        }
        $necesita = memory_get_usage() + ($ancho * $alto + self::LADO_MAXIMO ** 2) * 5;
        if ($necesita <= $limite) {
            return true;
        }
        @ini_set('memory_limit', (string) $necesita);

        return ini_parse_quantity((string) ini_get('memory_limit')) >= $necesita;
    }

    /**
     * Al volver a dibujarla se pierde la marca de orientación del teléfono: si no se aplica, la foto queda de lado.
     */
    private static function enderezar(GdImage $imagen, string $contenido): GdImage
    {
        if (! function_exists('exif_read_data') || (new finfo(FILEINFO_MIME_TYPE))->buffer($contenido) !== 'image/jpeg') {
            return $imagen;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contenido));
        $grados = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => 270,
            8 => 90,
            default => 0,
        };
        if ($grados === 0) {
            return $imagen;
        }
        $girada = imagerotate($imagen, $grados, 0);

        return $girada === false ? $imagen : $girada;
    }

    /**
     * Sobre fondo blanco: JPEG no tiene transparencia, y lo transparente saldría negro.
     */
    private static function enTamano(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $escala = min(1.0, self::LADO_MAXIMO / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $salida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($salida, 0, 0, (int) imagecolorallocate($salida, 255, 255, 255));
        imagecopyresampled($salida, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        return $salida;
    }
}
