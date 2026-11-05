<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\EstadoGrupo;
use App\Enums\NivelAviso;
use App\Models\Aviso;
use App\Models\Grupo;
use App\Reservas\GestorGrupos;
use App\Reservas\ReservaRechazada;
use Illuminate\Console\Command;

/**
 * Pasada la fecha límite de la lista, las habitaciones sin responsable se vuelven a vender, en los grupos que así lo pactaron.
 */
class LiberarGrupos extends Command
{
    protected $signature = 'alojaflow:grupos';

    protected $description = 'Libera las habitaciones sin responsable de los grupos con la fecha límite vencida';

    public function handle(GestorGrupos $grupos): int
    {
        Grupo::query()->with('propiedad')->where('liberar_sin_responsable', true)->whereNotNull('fecha_limite_lista')->where('estado', '!=', EstadoGrupo::Cancelado->value)
            ->get()
            ->filter(fn (Grupo $grupo): bool => (string) $grupo->fecha_limite_lista?->format('Y-m-d') < $grupo->propiedad->hoy()->format('Y-m-d'))
            ->each(function (Grupo $grupo) use ($grupos): void {
                try {
                    $liberadas = $grupos->liberarSinResponsable($grupo);
                } catch (ReservaRechazada $e) {
                    report($e);

                    return;
                }
                if ($liberadas > 0) {
                    Aviso::abrir($grupo->propiedad_id, 'grupo_habitaciones_liberadas', NivelAviso::Advertencia, ['grupo' => $grupo->nombre, 'habitaciones' => $liberadas]);
                }
            });

        return self::SUCCESS;
    }
}
