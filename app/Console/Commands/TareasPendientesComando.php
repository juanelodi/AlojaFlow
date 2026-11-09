<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Programador\TareasPendientes;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Corre lo pendiente del programador sin depender del minuto en que arranca (D-169). Para un cron que no corre cada
 * minuto (DreamHost: cada 10) y para AlojaFlow App, que arranca las instancias una tras otra. Sale con 1 si alguna
 * tarea falló; las demás corren igual.
 */
class TareasPendientesComando extends Command
{
    protected $signature = 'alojaflow:tareas-pendientes
        {--ventana='.TareasPendientes::VENTANA_MINUTOS.' : Minutos hacia atrás que cuenta una tarea que nunca ha corrido aquí}
        {--json : Responder en JSON (para AlojaFlow App)}';

    protected $description = 'Corre las tareas programadas que tocaron desde su última vez, sin depender del minuto';

    public function handle(TareasPendientes $tareas): int
    {
        $ventana = filter_var($this->option('ventana'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1440]]);
        if ($ventana === false) {
            $this->error(__('consola.tareas_pendientes.ventana'));

            return self::INVALID;
        }
        $inicio = CarbonImmutable::now('UTC');
        $reloj = hrtime(true);
        $resultados = $tareas->ejecutar($ventana);
        $fallidas = count(array_filter($resultados, fn (array $r): bool => $r['estado'] === 'fallida'));

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'inicio' => $inicio->toIso8601String(),
                'duracion_ms' => (int) round((hrtime(true) - $reloj) / 1e6),
                'corridas' => count($resultados),
                'fallidas' => $fallidas,
                'tareas' => $resultados,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } elseif ($resultados === []) {
            $this->info(__('consola.tareas_pendientes.nada'));
        } else {
            $this->table(
                [__('consola.tareas_pendientes.tarea'), __('consola.tareas_pendientes.estado'), 'ms'],
                array_map(fn (array $r): array => [$r['tarea'], $r['estado'].(isset($r['error']) ? ' ('.$r['error'].')' : ''), $r['duracion_ms']], $resultados),
            );
        }

        return $fallidas > 0 ? self::FAILURE : self::SUCCESS;
    }
}
