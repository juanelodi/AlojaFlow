<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Huellas, HMAC y códigos aleatorios (modelo-datos.md §1.2 y §1.8; D-022).
 */
final class Huellas
{
    // Sin caracteres que se confunden (0/O, 1/I/L).
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * SHA-256 de un arreglo, con las claves en orden fijo.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function datos(array $datos): string
    {
        ksort($datos);

        return hash('sha256', (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    /**
     * HMAC para buscar por coincidencia exacta un dato que se guarda cifrado.
     */
    public static function hmac(string $proposito, string $valor): string
    {
        return hash_hmac('sha256', $valor, self::clave($proposito));
    }

    public static function codigoPublico(int $longitud = 10): string
    {
        $codigo = '';
        $maximo = strlen(self::ALFABETO) - 1;
        for ($i = 0; $i < $longitud; $i++) {
            $codigo .= self::ALFABETO[random_int(0, $maximo)];
        }

        return $codigo;
    }

    /**
     * Clave propia de cada propósito, derivada de APP_KEY con HKDF.
     */
    private static function clave(string $proposito): string
    {
        $clave = (string) config('app.key');
        if (str_starts_with($clave, 'base64:')) {
            $clave = (string) base64_decode(substr($clave, 7), true);
        }
        if ($clave === '') {
            throw new RuntimeException('APP_KEY no está definida.');
        }

        return hash_hkdf('sha256', $clave, 32, 'alojaflow:'.$proposito);
    }
}
