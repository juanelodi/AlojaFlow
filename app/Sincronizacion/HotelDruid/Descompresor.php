<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use Throwable;

/**
 * Descomprime la copia si llegó en gzip (subordinacion.md B7). Se reconoce por sus dos primeros bytes, no por lo que
 * diga la cabecera de la respuesta, y se descomprime por pedazos, con un tope: un archivo pequeño puede inflarse
 * hasta llenar el disco.
 */
class Descompresor
{
    public const FIRMA_GZIP = "\x1f\x8b";

    private const PASO = 'descomprimir';

    /** Pedazos chicos a propósito: gzip puede inflar mil veces, y cada pedazo descomprimido pasa por la memoria. */
    private const PEDAZO = 16384;

    /**
     * @return bool verdadero si era gzip y lo descomprimido quedó en $destino; falso si $origen ya era la copia tal cual
     */
    public function descomprimirSiHaceFalta(string $origen, string $destino, int $limite): bool
    {
        $entrada = fopen($origen, 'rb');
        if ($entrada === false) {
            throw new ErrorSincronizacion(CodigoError::ErrorInterno, self::PASO);
        }
        try {
            if (fread($entrada, 2) !== self::FIRMA_GZIP) {
                return false;
            }
            rewind($entrada);
            $salida = fopen($destino, 'wb');
            if ($salida === false) {
                throw new ErrorSincronizacion(CodigoError::ErrorInterno, self::PASO);
            }
            try {
                $this->inflar($entrada, $salida, $limite);
            } finally {
                fclose($salida);
            }
        } finally {
            fclose($entrada);
        }

        return true;
    }

    /**
     * @param  resource  $entrada
     * @param  resource  $salida
     */
    private function inflar($entrada, $salida, int $limite): void
    {
        $descompresor = inflate_init(ZLIB_ENCODING_GZIP);
        if ($descompresor === false) {
            throw new ErrorSincronizacion(CodigoError::ErrorInterno, self::PASO);
        }
        $escritos = 0;
        while (! feof($entrada)) {
            $pedazo = fread($entrada, self::PEDAZO);
            if ($pedazo === false || $pedazo === '') {
                continue;
            }
            try {
                $claro = inflate_add($descompresor, $pedazo, ZLIB_SYNC_FLUSH);
            } catch (Throwable) {
                // PHP avisa de los datos dañados con una advertencia, que Laravel convierte en excepción.
                $claro = false;
            }
            if ($claro === false) {
                throw new ErrorSincronizacion(CodigoError::GzipDanado, self::PASO);
            }
            $escritos += strlen($claro);
            if ($escritos > $limite) {
                throw new ErrorSincronizacion(CodigoError::TamanoExcedido, self::PASO);
            }
            fwrite($salida, $claro);
            if (inflate_get_status($descompresor) === ZLIB_STREAM_END) {
                return;
            }
        }

        // Se acabó el archivo sin llegar al final del gzip: llegó cortado.
        throw new ErrorSincronizacion(CodigoError::GzipDanado, self::PASO);
    }
}
