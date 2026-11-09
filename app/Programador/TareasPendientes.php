<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Programador;

use App\Models\Ajuste;
use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lo pendiente del programador, sin depender del minuto en que se arranca (D-169). schedule:run solo corre lo que
 * toca en ese minuto: con un cron cada 10 minutos, y varias instancias una tras otra, una que empieza a las 3:11 se
 * salta la copia de las 3:10. Aquí cada tarea del horario (routes/console.php, sin repetirlo) corre si le tocó desde
 * la última vez que corrió, una sola vez aunque le haya tocado varias.
 *
 * - La última vez de cada tarea se guarda aparte; también la anota schedule:run (AppServiceProvider), así que los dos
 *   pueden convivir sin repetir lo de hora fija.
 * - Se marca antes de correr y bajo un candado: si dos rondas se enciman, la segunda ya la ve hecha.
 * - Una tarea que nunca ha corrido aquí cuenta lo que le tocó en la última ventana (15 minutos por omisión): la copia
 *   de anoche no corre a mediodía la primera vez.
 */
final class TareasPendientes
{
    public const VENTANA_MINUTOS = 15;

    private const PREFIJO = 'programador.ultima.';

    public function __construct(private readonly Application $app, private readonly Schedule $horario)
    {
    }

    /**
     * @return list<array{tarea: string, estado: string, debia: string, duracion_ms: int, error?: string}>
     */
    public function ejecutar(int $ventanaMinutos = self::VENTANA_MINUTOS): array
    {
        $resultados = [];
        foreach ($this->horario->events() as $evento) {
            $debia = $this->pendienteDesde($evento, $ventanaMinutos);
            if ($debia === null || ! $this->puedeCorrer($evento) || ! $this->apartar($evento, $debia)) {
                continue;
            }
            $resultados[] = $this->correr($evento, $debia);
        }

        return $resultados;
    }

    /**
     * El nombre con que se reconoce: el de un callback, o el comando de artisan con sus opciones.
     */
    public static function nombre(Event $evento): string
    {
        if (filled($evento->description)) {
            return (string) $evento->description;
        }

        return preg_match('/artisan["\']?\s+(.+)$/', (string) $evento->command, $partes) === 1 ? trim($partes[1]) : (string) $evento->command;
    }

    /**
     * Lo llama schedule:run al empezar cada tarea, para que esta ronda no la repita.
     */
    public static function anotar(Event $evento): void
    {
        Ajuste::query()->updateOrCreate(['clave' => self::clave($evento)], ['valor' => CarbonImmutable::now('UTC')->toIso8601String()]);
    }

    public static function ultima(Event $evento): ?CarbonImmutable
    {
        $valor = Ajuste::query()->where('clave', self::clave($evento))->value('valor');

        return is_string($valor) ? CarbonImmutable::parse($valor) : null;
    }

    /**
     * El último momento en que le tocó y que todavía no se ha corrido, o null.
     */
    private function pendienteDesde(Event $evento, int $ventanaMinutos): ?CarbonImmutable
    {
        $zona = $evento->timezone ?? (string) config('app.timezone');
        $ahora = CarbonImmutable::now($zona);
        $debia = CarbonImmutable::instance((new CronExpression($evento->expression))->getPreviousRunDate($ahora, 0, true, (string) $zona))->utc();
        $ultima = self::ultima($evento) ?? $ahora->utc()->subMinutes($ventanaMinutos)->subSecond();

        return $debia->greaterThan($ultima) ? $debia : null;
    }

    private function puedeCorrer(Event $evento): bool
    {
        if ($this->app->isDownForMaintenance() && ! $evento->runsInMaintenanceMode()) {
            return false;
        }

        return $evento->runsInEnvironment($this->app->environment()) && $evento->filtersPass($this->app);
    }

    /**
     * Bajo un candado, vuelve a leer la última vez y la marca: dos rondas a la vez no corren la misma tarea.
     */
    private function apartar(Event $evento, CarbonImmutable $debia): bool
    {
        $candado = Cache::lock('alojaflow.tareas-pendientes.'.sha1(self::nombre($evento)), 60);
        if (! $candado->get()) {
            return false;
        }
        try {
            $ultima = self::ultima($evento);
            if ($ultima !== null && $ultima->greaterThanOrEqualTo($debia)) {
                return false;
            }
            self::anotar($evento);

            return true;
        } finally {
            $candado->release();
        }
    }

    /**
     * @return array{tarea: string, estado: string, debia: string, duracion_ms: int, error?: string}
     */
    private function correr(Event $evento, CarbonImmutable $debia): array
    {
        $inicio = hrtime(true);
        $resultado = ['tarea' => self::nombre($evento), 'estado' => 'correcta', 'debia' => $debia->toIso8601String()];
        try {
            $evento->run($this->app);
            if ($evento->skippedBecauseOverlapping) {
                // La está corriendo otro proceso (withoutOverlapping): no es un fallo.
                $resultado['estado'] = 'ocupada';
            } elseif (! $evento instanceof CallbackEvent && (int) $evento->exitCode !== 0) {
                $resultado['estado'] = 'fallida';
                $resultado['error'] = 'exit '.(int) $evento->exitCode;
            }
        } catch (Throwable $e) {
            report($e);
            $resultado['estado'] = 'fallida';
            $resultado['error'] = class_basename($e);
        }

        return $resultado + ['duracion_ms' => (int) round((hrtime(true) - $inicio) / 1e6)];
    }

    private static function clave(Event $evento): string
    {
        return self::PREFIJO.substr(sha1(self::nombre($evento)), 0, 20);
    }
}
