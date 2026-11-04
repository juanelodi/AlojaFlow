<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Instalacion;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * En un hosting compartido, armar la configuración, las rutas y los componentes en cada visita es lo
 * que más tarda. Se dejan listos al instalar, al actualizar y al restaurar. Si alguno falla, la
 * aplicación funciona igual, solo que más lenta. Las vistas se compilan solas la primera vez.
 */
final class CachesArranque
{
    private const COMANDOS = ['config:cache', 'route:cache', 'event:cache', 'filament:optimize'];

    public static function rehacer(): void
    {
        // En desarrollo y en las pruebas estorban: cada cambio tendría que borrarlas.
        if (! app()->isProduction()) {
            return;
        }
        foreach (self::COMANDOS as $comando) {
            try {
                Artisan::call($comando);
            } catch (Throwable $e) {
                Log::warning('Cachés de arranque: '.$comando.': '.$e->getMessage());
            }
        }
    }
}
