<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Autoridades\GestorEnvios;
use App\Models\Propiedad;
use App\Paises\Paises;
use Illuminate\Console\Command;

/**
 * Comunica al registro de viajeros lo que falte y pregunta qué pasó con lo que ya se mandó (D-100).
 */
class ComunicarAutoridades extends Command
{
    protected $signature = 'alojaflow:autoridades';

    protected $description = 'Comunica las reservas y las llegadas al registro de viajeros del país';

    public function handle(GestorEnvios $envios): int
    {
        $propiedades = Propiedad::query()->where('activa', true)->whereNotNull('paquete_pais')->get()
            ->filter(fn (Propiedad $propiedad): bool => Paises::de($propiedad)->registroViajeros() !== null);
        foreach ($propiedades as $propiedad) {
            $resultado = $envios->correr($propiedad);
            if ($resultado['enviadas'] + $resultado['respondidas'] > 0) {
                $this->line($propiedad->codigo.': '.$resultado['enviadas'].' / '.$resultado['respondidas']);
            }
        }

        return self::SUCCESS;
    }
}
