<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Copias;

use SensitiveParameter;

/**
 * Firma AWS Signature Version 4 para un almacenamiento compatible con S3 (DreamObjects, Backblaze B2,
 * Wasabi, Cloudflare R2, AWS), sin SDK: lo único que se hace es subir un archivo y probar la conexión.
 */
final class FirmaS3
{
    /**
     * @param  array<string, string>  $cabeceras  sin Authorization; host, x-amz-date y x-amz-content-sha256 incluidas
     * @param  array<string, string>  $consulta
     * @return string el valor de la cabecera Authorization
     */
    public static function autorizacion(
        string $metodo,
        string $ruta,
        array $consulta,
        array $cabeceras,
        string $hashCuerpo,
        string $region,
        string $llave,
        #[SensitiveParameter] string $secreto,
    ): string {
        $normalizadas = [];
        foreach ($cabeceras as $nombre => $valor) {
            $normalizadas[strtolower($nombre)] = trim(preg_replace('/\s+/', ' ', $valor) ?? '');
        }
        ksort($normalizadas);
        ksort($consulta);
        $firmadas = implode(';', array_keys($normalizadas));
        $canonica = implode("\n", [
            strtoupper($metodo),
            self::rutaCanonica($ruta),
            implode('&', array_map(fn (string $k, string $v): string => rawurlencode($k).'='.rawurlencode($v), array_keys($consulta), $consulta)),
            implode('', array_map(fn (string $k, string $v): string => $k.':'.$v."\n", array_keys($normalizadas), $normalizadas)),
            $firmadas,
            $hashCuerpo,
        ]);
        $fechaHora = $normalizadas['x-amz-date'];
        $fecha = substr($fechaHora, 0, 8);
        $alcance = $fecha.'/'.$region.'/s3/aws4_request';
        $aFirmar = implode("\n", ['AWS4-HMAC-SHA256', $fechaHora, $alcance, hash('sha256', $canonica)]);
        $clave = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $fecha, 'AWS4'.$secreto, true), true), true), true);

        return 'AWS4-HMAC-SHA256 Credential='.$llave.'/'.$alcance.',SignedHeaders='.$firmadas.',Signature='.hash_hmac('sha256', $aFirmar, $clave);
    }

    private static function rutaCanonica(string $ruta): string
    {
        return implode('/', array_map(fn (string $parte): string => rawurlencode(rawurldecode($parte)), explode('/', $ruta)));
    }
}
