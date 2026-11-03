<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

/**
 * Descomprime la copia si llegó en gzip, en flujo y con límite de tamaño (subordinacion.md B7).
 */
class Descompresor
{
    /**
     * @return bool verdadero si el origen era gzip y se escribió el destino
     */
    public function descomprimirSiHaceFalta(string $origen, string $destino, int $limite): bool
    {
        $manejador = fopen($origen, 'rb');
        $firma = $manejador === false ? '' : (string) fread($manejador, 2);
        if ($manejador !== false) {
            fclose($manejador);
        }
        if ($firma !== "\x1f\x8b") {
            return false;
        }

        $gz = gzopen($origen, 'rb');
        $salida = fopen($destino, 'wb');
        if ($gz === false || $salida === false) {
            throw new ErrorSincronizacion(CodigoError::GzipDanado, 'descomprimir');
        }

        $total = 0;
        try {
            while (! gzeof($gz)) {
                $bloque = gzread($gz, 1 << 20);
                if ($bloque === false) {
                    throw new ErrorSincronizacion(CodigoError::GzipDanado, 'descomprimir');
                }
                $total += strlen($bloque);
                if ($total > $limite) {
                    throw new ErrorSincronizacion(CodigoError::TamanoExcedido, 'descomprimir');
                }
                fwrite($salida, $bloque);
            }
        } finally {
            gzclose($gz);
            fclose($salida);
        }

        return true;
    }
}
