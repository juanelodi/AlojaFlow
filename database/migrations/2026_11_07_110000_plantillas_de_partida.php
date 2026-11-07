<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use App\Comunicacion\PlantillasBase;
use App\Documentos\DocumentosDePartida;
use App\Models\DocumentoPropio;
use App\Models\Plantilla;
use App\Models\Propiedad;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

return new class () extends Migration {
    /**
     * Las propiedades que ya existen reciben lo de partida (D-147). Quien ya usaba plantillas o documentos solo recibe lo nuevo: los de antes pudo renombrarlos o
     * borrarlos a propósito. Si algo falla no detiene la actualización: se puede crear con el botón de cada pantalla.
     */
    public function up(): void
    {
        foreach (Propiedad::query()->get() as $propiedad) {
            try {
                PlantillasBase::sembrar($propiedad, Plantilla::query()->where('propiedad_id', $propiedad->id)->exists() ? ['instrucciones', 'pago_pendiente', 'pago_recibido', 'cambio', 'antes_salida', 'pide_opinion'] : null);
                DocumentosDePartida::sembrar($propiedad, DocumentoPropio::query()->where('propiedad_id', $propiedad->id)->exists() ? ['reglamento', 'limpieza'] : null);
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
