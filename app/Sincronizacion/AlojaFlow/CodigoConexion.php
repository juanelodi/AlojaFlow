<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\AlojaFlow;

use SensitiveParameter;

/**
 * El texto que se copia de la principal y se pega en la subordinada: dónde está y con qué token entra.
 */
final class CodigoConexion
{
    private const PREFIJO = 'AF1-';

    public static function armar(string $urlBase, #[SensitiveParameter] string $token): string
    {
        return self::PREFIJO.rtrim(strtr(base64_encode((string) json_encode(['u' => $urlBase, 't' => $token], JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    /**
     * @return array{url: string, token: string}|null null si no es un código de AlojaFlow
     */
    public static function leer(#[SensitiveParameter] string $codigo): ?array
    {
        $codigo = trim($codigo);
        if (! str_starts_with($codigo, self::PREFIJO)) {
            return null;
        }
        $json = base64_decode(strtr(substr($codigo, strlen(self::PREFIJO)), '-_', '+/'), true);
        $datos = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($datos) || ! is_string($datos['u'] ?? null) || ! is_string($datos['t'] ?? null) || $datos['u'] === '' || ! preg_match('/^[0-9a-f]{64}$/', $datos['t'])) {
            return null;
        }

        return ['url' => $datos['u'], 'token' => $datos['t']];
    }
}
