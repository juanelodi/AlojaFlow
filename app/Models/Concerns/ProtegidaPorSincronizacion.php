<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Sincronizacion\ContextoSincronizacion;
use LogicException;

/**
 * Una fila con sincronizada = true la controla la principal: solo la sincronización puede
 * cambiarla o borrarla (modelo-datos.md §2, regla 2).
 */
trait ProtegidaPorSincronizacion
{
    public static function bootProtegidaPorSincronizacion(): void
    {
        $proteger = static function (self $modelo): void {
            if ($modelo->getOriginal('sincronizada') && ! ContextoSincronizacion::activo()) {
                throw new LogicException(__('sincronizacion.fila_protegida'));
            }
        };

        static::updating($proteger);
        static::deleting($proteger);
    }

    public function estaSincronizada(): bool
    {
        return (bool) $this->getAttribute('sincronizada');
    }
}
