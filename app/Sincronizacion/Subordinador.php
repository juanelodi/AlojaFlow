<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Enums\OrigenEjecucion;
use App\Models\EjecucionSincronizacion;
use App\Models\Principal;

/**
 * Cómo se copia de una principal: uno por cada sistema del que se puede copiar. Ninguno escribe en ella.
 */
interface Subordinador
{
    public function sincronizar(Principal $principal, OrigenEjecucion $origen): EjecucionSincronizacion;

    /**
     * Comprueba la conexión sin cambiar nada; lanza ErrorSincronizacion si no funciona.
     */
    public function probar(Principal $principal): void;
}
