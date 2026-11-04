<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Instalacion;

/**
 * Versiones de PHP con fallos críticos corregidos. Por ahora, CVE-2026-109618 y CVE-2026-109619 (ejecución
 * de código sin iniciar sesión, php.net 2026-10-21). Avisa y no bloquea: un hosting puede haber llevado
 * el parche a una versión con un número anterior. El instalador lleva la misma lista (instalador/instalar.php).
 */
final class VersionPhp
{
    public const CORREGIDAS = ['8.2' => '8.2.35', '8.3' => '8.3.36', '8.4' => '8.4.27', '8.5' => '8.5.12'];

    /**
     * La versión a la que hay que subir, o null si no se conoce ningún fallo crítico en esta.
     */
    public static function falta(string $version): ?string
    {
        $corregida = self::CORREGIDAS[implode('.', array_slice(explode('.', $version), 0, 2))] ?? null;

        return $corregida !== null && version_compare($version, $corregida, '<') ? $corregida : null;
    }
}
