<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\PlantillasBase;
use App\Documentos\DocumentosDePartida;
use App\Models\Propiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class () extends Migration {
    /**
     * Las propiedades que ya existen también reciben lo que les falte (D-147). Lo que ya tengan, aunque se haya cambiado, se queda; y si algo falla no detiene
     * la actualización: se puede crear con el botón de cada pantalla.
     */
    public function up(): void
    {
        foreach (Propiedad::query()->get() as $propiedad) {
            try {
                PlantillasBase::sembrar($propiedad);
                DocumentosDePartida::sembrar($propiedad);
            } catch (Throwable $e) {
                Log::warning('Plantillas de partida sin crear: '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        // Pueden haberse editado: no se borran.
    }
};
