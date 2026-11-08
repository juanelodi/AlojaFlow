<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\MejoraContinua;

use RuntimeException;

/**
 * El receptor no respondió como se esperaba. Lleva un código corto (sin_conexion, credenciales,
 * demasiadas_peticiones…) y el estado HTTP, sin nada de lo que se mandó.
 */
final class ErrorReceptor extends RuntimeException
{
    public function __construct(public readonly string $motivo, public readonly int $estado)
    {
        parent::__construct($motivo.($estado > 0 ? ' (HTTP '.$estado.')' : ''));
    }

    /**
     * Si tiene sentido reintentar más tarde (sin conexión, saturado, error del servidor).
     */
    public function reintentable(): bool
    {
        return $this->estado === 0 || $this->estado === 429 || $this->estado >= 500 || $this->motivo === 'credenciales';
    }
}
