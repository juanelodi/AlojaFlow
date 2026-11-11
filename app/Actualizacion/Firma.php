<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Actualizacion;

use RuntimeException;
use SensitiveParameter;

/**
 * Firma Ed25519 (libsodium) de los paquetes de actualización. Solo se instala un paquete cuyo
 * manifiesto esté firmado con la clave secreta de quien publica las versiones (D-033).
 */
final class Firma
{
    /**
     * @return array{publica: string, secreta: string} claves en base64
     */
    public static function generarClaves(): array
    {
        $par = sodium_crypto_sign_keypair();

        return [
            'publica' => base64_encode(sodium_crypto_sign_publickey($par)),
            'secreta' => base64_encode(sodium_crypto_sign_secretkey($par)),
        ];
    }

    public static function firmar(string $mensaje, #[SensitiveParameter] string $secretaBase64): string
    {
        $secreta = base64_decode(trim($secretaBase64), true);
        if ($secreta === false || strlen($secreta) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('La clave secreta de firma no es válida.');
        }

        return base64_encode(sodium_crypto_sign_detached($mensaje, $secreta));
    }

    public static function verificar(string $mensaje, string $firmaBase64, string $publicaBase64): bool
    {
        $firma = base64_decode(trim($firmaBase64), true);
        $publica = base64_decode(trim($publicaBase64), true);
        if ($firma === false || $publica === false
            || strlen($firma) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($firma, $mensaje, $publica);
    }
}
