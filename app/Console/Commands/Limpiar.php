<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\Actualizador;
use App\Enums\EstadoEnvio;
use App\Models\Actualizacion;
use App\Models\EjecucionSincronizacion;
use App\Models\Envio;
use App\Models\Principal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Borra lo vencido según los plazos de conservación (modelo-datos.md §8).
 */
class Limpiar extends Command
{
    protected $signature = 'alojaflow:limpiar';

    protected $description = 'Borra los registros vencidos (ejecuciones de sincronización, copias de actualizaciones…)';

    public function handle(Actualizador $actualizador): int
    {
        $vigentes = Principal::query()->whereNotNull('ejecucion_vigente_id')->pluck('ejecucion_vigente_id');
        $borradas = EjecucionSincronizacion::query()
            ->where('iniciada_en', '<', now()->subDays((int) config('alojaflow.subordinacion.dias_conservar_ejecuciones')))
            ->whereNotIn('id', $vigentes)
            ->delete();

        $this->info("Ejecuciones de sincronización borradas: $borradas");

        // El texto de los mensajes lleva datos personales: el historial se queda, el cuerpo no (§8).
        Envio::query()->whereNotNull('cuerpo')->where('creado_en', '<', now()->subDays(90))->whereNotIn('estado', [EstadoEnvio::Pendiente->value])->update(['cuerpo' => null]);
        $disco = Storage::disk('local');
        foreach ($disco->files('subidas-temporales') as $temporal) {
            if ($disco->lastModified($temporal) < now()->subDay()->getTimestamp()) {
                $disco->delete($temporal);
            }
        }

        // Las copias de cada actualización (base de datos y archivos sustituidos) llevan datos
        // personales: solo se guardan las de las últimas.
        $conservar = Actualizacion::query()->latest('id')->limit((int) config('alojaflow.actualizaciones.copias_conservadas'))->pluck('id')->all();
        $copias = 0;
        foreach (glob($actualizador->carpeta().'/*', GLOB_ONLYDIR) ?: [] as $carpeta) {
            if (ctype_digit(basename($carpeta)) && ! in_array((int) basename($carpeta), $conservar, true)) {
                File::deleteDirectory($carpeta);
                $copias++;
            }
        }
        $this->info("Copias de actualizaciones borradas: $copias");

        return self::SUCCESS;
    }
}
