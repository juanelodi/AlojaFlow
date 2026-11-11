<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Demo;

/**
 * Instalación pública de demostración (D-044): una aparte, solo con datos ficticios, con un usuario
 * a la vista y que se borra cada noche. Con este modo apagado, nada de esto existe.
 */
final class ModoDemo
{
    /** A propósito están a la vista: se muestran en la pantalla de inicio de sesión. */
    public const CORREO = 'demo@example.com';

    public const CONTRASENA = 'demo-alojaflow';

    public static function activo(): bool
    {
        return (bool) config('alojaflow.modo_demo', false);
    }
}
