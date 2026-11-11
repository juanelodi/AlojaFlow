<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Instalacion;

use App\Models\User;
use App\Support\Ajustes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * El enlace con el que quien acaba de instalar entra sin volver a escribir su contraseña (D-071). Lo
 * recibe solo el navegador dueño del instalador, sirve una vez y caduca: se guarda su huella, no el enlace.
 */
final class EnlaceEntrada
{
    /** El de quien instaló (D-071, D-162). */
    public const USO_INSTALACION = 'instalacion';

    /** El del soporte de AlojaFlow (D-166): cada uno tiene su lugar, y pedir uno no gasta el otro. */
    public const USO_SOPORTE = 'soporte';

    private const MINUTOS = 30;

    public static function crear(User $usuario, int $minutos = self::MINUTOS, string $uso = self::USO_INSTALACION): string
    {
        $token = Str::lower(Str::random(64));
        Ajustes::guardar(self::ajuste($uso), [
            'huella' => hash('sha256', $token),
            'usuario' => $usuario->id,
            'expira' => now('UTC')->addMinutes($minutos)->toIso8601String(),
        ]);

        return rtrim((string) config('app.url'), '/').route('entrada-instalacion', ['token' => $token], false);
    }

    /**
     * El token correcto gasta el enlace, y uno equivocado no le hace nada. Si mientras tanto se activó la verificación en dos pasos, ya no
     * sirve: este enlace no la pide.
     */
    public static function usar(string $token): ?User
    {
        foreach ([self::USO_INSTALACION, self::USO_SOPORTE] as $uso) {
            $datos = Ajustes::obtener(self::ajuste($uso));
            if (! is_array($datos) || ! hash_equals((string) ($datos['huella'] ?? ''), hash('sha256', $token))) {
                continue;
            }
            Ajustes::borrar(self::ajuste($uso));
            if (CarbonImmutable::parse((string) $datos['expira'])->isPast()) {
                return null;
            }
            $usuario = User::query()->find((int) $datos['usuario']);

            return $usuario !== null && $usuario->puedeEntrar() && blank($usuario->getAppAuthenticationSecret()) ? $usuario : null;
        }

        return null;
    }

    private static function ajuste(string $uso): string
    {
        return $uso.'.enlace_entrada';
    }
}
