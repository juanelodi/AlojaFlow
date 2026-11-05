<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
    private const AJUSTE = 'instalacion.enlace_entrada';

    private const MINUTOS = 30;

    public static function crear(User $usuario): string
    {
        $token = Str::lower(Str::random(64));
        Ajustes::guardar(self::AJUSTE, [
            'huella' => hash('sha256', $token),
            'usuario' => $usuario->id,
            'expira' => now('UTC')->addMinutes(self::MINUTOS)->toIso8601String(),
        ]);

        return rtrim((string) config('app.url'), '/').route('entrada-instalacion', ['token' => $token], false);
    }

    /**
     * El token correcto gasta el enlace, y uno equivocado no le hace nada. Si mientras tanto se activó la verificación en dos pasos, ya no
     * sirve: este enlace no la pide.
     */
    public static function usar(string $token): ?User
    {
        $datos = Ajustes::obtener(self::AJUSTE);
        if (! is_array($datos) || ! hash_equals((string) ($datos['huella'] ?? ''), hash('sha256', $token))) {
            return null;
        }
        Ajustes::borrar(self::AJUSTE);
        if (CarbonImmutable::parse((string) $datos['expira'])->isPast()) {
            return null;
        }
        $usuario = User::query()->find((int) $datos['usuario']);

        return $usuario !== null && $usuario->activo && blank($usuario->getAppAuthenticationSecret()) ? $usuario : null;
    }
}
