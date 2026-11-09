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
 * Cifra una copia antes de que salga del servidor (D-152), con una clave que elige el administrador: quien
 * guarde el archivo (el proveedor de almacenamiento) no puede leer los datos de los huéspedes. libsodium:
 * la clave sale de la contraseña con Argon2id y el archivo va en bloques autenticados (secretstream), así
 * que un archivo alterado o cortado no se descifra.
 *
 * Formato: «AFCOPIA1» + sal (16) + cabecera del stream (24) + bloques (4 bytes de largo + bloque cifrado).
 */
final class CifradoCopia
{
    public const MARCA = 'AFCOPIA1';

    private const BLOQUE = 1048576;

    public static function esCifrada(string $ruta): bool
    {
        $f = @fopen($ruta, 'rb');
        if ($f === false) {
            return false;
        }
        $inicio = (string) fread($f, strlen(self::MARCA));
        fclose($f);

        return $inicio === self::MARCA;
    }

    /**
     * @throws ErrorCopia
     */
    public static function cifrar(string $origen, string $destino, #[SensitiveParameter] string $clave): void
    {
        $sal = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
        $llave = self::llave($clave, $sal);
        [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($llave);
        $entrada = fopen($origen, 'rb');
        $salida = fopen($destino, 'wb');
        if ($entrada === false || $salida === false) {
            throw new ErrorCopia(__('copias.error.no_escribe'));
        }
        try {
            fwrite($salida, self::MARCA.$sal.$cabecera);
            $tamano = (int) filesize($origen);
            $leido = 0;
            do {
                $bloque = (string) fread($entrada, self::BLOQUE);
                $leido += strlen($bloque);
                $etiqueta = $leido >= $tamano ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE;
                $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push($estado, $bloque, '', $etiqueta);
                fwrite($salida, pack('N', strlen($cifrado)).$cifrado);
            } while ($etiqueta !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($entrada);
            fclose($salida);
            sodium_memzero($llave);
        }
    }

    /**
     * @throws ErrorCopia si la clave no es la correcta o el archivo está dañado
     */
    public static function descifrar(string $origen, string $destino, #[SensitiveParameter] string $clave): void
    {
        $entrada = fopen($origen, 'rb');
        $salida = fopen($destino, 'wb');
        if ($entrada === false || $salida === false) {
            throw new ErrorCopia(__('copias.error.no_escribe'));
        }
        try {
            $inicio = (string) fread($entrada, strlen(self::MARCA) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            if (! str_starts_with($inicio, self::MARCA) || strlen($inicio) < strlen(self::MARCA) + SODIUM_CRYPTO_PWHASH_SALTBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES) {
                throw new ErrorCopia(__('copias.externa.error_clave'));
            }
            $sal = substr($inicio, strlen(self::MARCA), SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $cabecera = substr($inicio, strlen(self::MARCA) + SODIUM_CRYPTO_PWHASH_SALTBYTES);
            $llave = self::llave($clave, $sal);
            $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull($cabecera, $llave);
            sodium_memzero($llave);
            $final = false;
            while (! $final) {
                $largo = fread($entrada, 4);
                if ($largo === false || strlen($largo) !== 4) {
                    throw new ErrorCopia(__('copias.externa.error_clave'));
                }
                $tamano = (int) unpack('N', $largo)[1];
                $cifrado = $tamano > 0 && $tamano <= self::BLOQUE + 64 ? (string) fread($entrada, $tamano) : '';
                $resultado = sodium_crypto_secretstream_xchacha20poly1305_pull($estado, $cifrado);
                if ($resultado === false) {
                    throw new ErrorCopia(__('copias.externa.error_clave'));
                }
                [$bloque, $etiqueta] = $resultado;
                fwrite($salida, $bloque);
                $final = $etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL;
            }
        } finally {
            fclose($entrada);
            fclose($salida);
        }
    }

    private static function llave(#[SensitiveParameter] string $clave, string $sal): string
    {
        return sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES,
            $clave,
            $sal,
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,
        );
    }
}
