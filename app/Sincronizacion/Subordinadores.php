<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion;

use App\Enums\TipoPrincipal;
use App\Models\Principal;
use App\Sincronizacion\AlojaFlow\Sincronizador as SincronizadorAlojaFlow;
use App\Sincronizacion\HotelDruid\Sincronizador as SincronizadorHotelDruid;

/**
 * El subordinador que le toca a cada principal.
 */
final class Subordinadores
{
    public static function de(Principal $principal): Subordinador
    {
        // Sin match exhaustivo: entre poner los archivos y migrar, la columna todavía no existe y una tarea podría llegar aquí.
        return $principal->tipo === TipoPrincipal::AlojaFlow ? app(SincronizadorAlojaFlow::class) : app(SincronizadorHotelDruid::class);
    }
}
