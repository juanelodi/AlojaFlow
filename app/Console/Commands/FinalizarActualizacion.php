<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actualizacion\Actualizador;
use App\Actualizacion\ErrorActualizacion;
use App\Enums\EstadoActualizacion;
use App\Models\Actualizacion;
use Illuminate\Console\Command;

/**
 * Termina una actualización con los archivos ya puestos (fase 2), o deshace una que se cortó a
 * medias. La lanza alojaflow:actualizar y, con --pendientes, el programador cada minuto: así la
 * aplicación no se queda en mantenimiento si se cerró el navegador entre las dos fases.
 */
class FinalizarActualizacion extends Command
{
    /** Lo que se deja al navegador para pasar de la fase 1 a la 2 antes de terminarla aquí. */
    private const MINUTOS_SIN_TERMINAR = 2;

    /** Una que sigue «aplicando» pasado este tiempo murió a medias (el candado ya se soltó). */
    private const MINUTOS_INTERRUMPIDA = 30;

    protected $signature = 'alojaflow:finalizar-actualizacion
        {--actualizacion= : ID de una actualización concreta}
        {--pendientes : Solo las que llevan un rato sin terminar (lo usa el programador)}';

    protected $description = 'Termina una actualización con los archivos ya puestos, o deshace una que se cortó';

    public function handle(Actualizador $actualizador): int
    {
        $pendientes = (bool) $this->option('pendientes');
        $actualizacion = $this->option('actualizacion') !== null
            ? Actualizacion::query()->find((int) $this->option('actualizacion'))
            : $actualizador->pendiente();
        if ($actualizacion === null) {
            if (! $pendientes) {
                $this->info(__('actualizaciones.consola.nada_pendiente'));
            }

            return self::SUCCESS;
        }

        $quieta = fn (int $minutos): bool => $actualizacion->actualizado_en === null
            || $actualizacion->actualizado_en->lte(now()->subMinutes($minutos));
        try {
            if ($actualizacion->estado === EstadoActualizacion::ArchivosAplicados) {
                if ($pendientes && ! $quieta(self::MINUTOS_SIN_TERMINAR)) {
                    return self::SUCCESS;
                }
                $actualizador->finalizar($actualizacion);
                $this->info(__('actualizaciones.completada', ['version' => $actualizacion->version_nueva]));
            } elseif ($actualizacion->estado === EstadoActualizacion::Aplicando) {
                if (! $quieta(self::MINUTOS_INTERRUMPIDA)) {
                    if (! $pendientes) {
                        $this->warn(__('actualizaciones.error.en_curso'));
                    }

                    return self::SUCCESS;
                }
                $actualizador->recuperarInterrumpida($actualizacion);
                $this->warn(__('actualizaciones.error.interrumpida'));
            } elseif (! $pendientes) {
                $this->info($actualizacion->version_nueva.': '.$actualizacion->estado->etiqueta());
            }
        } catch (ErrorActualizacion $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
