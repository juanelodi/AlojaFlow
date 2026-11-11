<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * A quién se le cuentan los intentos en los límites por dirección. Con IPv6, un solo cliente tiene
 * miles de millones de direcciones dentro de su red /64: se cuenta la red, no cada dirección.
 */
final class ClaveCliente
{
    public static function de(Request $request): string
    {
        $ip = (string) $request->ip();
        if (str_contains($ip, ':') && ($binaria = inet_pton($ip)) !== false) {
            return bin2hex(substr($binaria, 0, 8)).'::/64';
        }

        return $ip;
    }
}
