<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoEjecucion;
use App\Enums\OrigenEjecucion;
use App\Models\Principal;
use App\Sincronizacion\HotelDruid\Sincronizador;
use Illuminate\Console\Command;

/**
 * Sincroniza las principales que tocan según su frecuencia, de una en una (A4). Lo lanza el
 * programador cada minuto; con --principal=ID sincroniza esa aunque no toque.
 */
class Sincronizar extends Command
{
    protected $signature = 'alojaflow:sincronizar {--principal= : ID de una principal concreta}';

    protected $description = 'Sincroniza las principales de HotelDruid que tocan';

    public function handle(Sincronizador $sincronizador): int
    {
        $ahora = now();
        $consulta = Principal::query()->with('propiedad')
            ->whereHas('propiedad', fn ($q) => $q->where('activa', true));

        if ($this->option('principal') !== null) {
            $consulta->whereKey((int) $this->option('principal'));
        }

        $principales = $consulta->get()->filter(
            fn (Principal $p): bool => $this->option('principal') !== null || $p->toca($ahora)
        );

        foreach ($principales as $principal) {
            $origen = $this->option('principal') !== null ? OrigenEjecucion::Manual : OrigenEjecucion::Programada;
            $ejecucion = $sincronizador->sincronizar($principal, $origen);
            $mensaje = $principal->propiedad->nombre.': '.$ejecucion->estado->etiqueta()
                .($ejecucion->codigo_error ? ' ('.$ejecucion->codigo_error.' '.$ejecucion->detalle.')' : '');
            $ejecucion->estado === EstadoEjecucion::Correcta ? $this->info($mensaje) : $this->warn($mensaje);
        }

        return self::SUCCESS;
    }
}
