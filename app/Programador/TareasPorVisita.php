<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Programador;

use App\Demo\ModoDemo;
use App\Support\Ajustes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Las tareas del programador cuando el hosting no tiene cron, o nadie lo ha creado: corren al
 * terminar de atender una visita, por turnos y con un tiempo máximo (D-039). En cuanto el cron da
 * señales de vida, esto se apaga solo.
 */
final class TareasPorVisita
{
    /** Tiempo sin latido a partir del cual se considera que no hay cron. */
    private const MINUTOS_SIN_CRON = 30;

    private const SEGUNDOS_MAXIMOS = 25;

    /**
     * Comando y cada cuántos minutos.
     *
     * @var array<string, int>
     */
    public const TAREAS = [
        'alojaflow:finalizar-actualizacion --pendientes' => 2,
        'alojaflow:sincronizar' => 5,
        'alojaflow:ical' => 15,
        'alojaflow:mensajes' => 5,
        'alojaflow:reservas-vencidas' => 5,
        'alojaflow:limpieza' => 15,
        'alojaflow:limpiar' => 1440,
        'alojaflow:copia --programada' => 1440,
    ];

    /**
     * @return array<string, int>
     */
    public static function tareas(): array
    {
        if (! ModoDemo::activo()) {
            return self::TAREAS;
        }
        $tareas = self::TAREAS;
        unset($tareas['alojaflow:copia --programada']);

        // Sin cron el reinicio no tiene hora: cada hora mira si hoy ya se hizo.
        return $tareas + ['alojaflow:demo-reiniciar --si-toca' => 60];
    }

    public static function anotarLatido(): void
    {
        Ajustes::guardar('programador.latido', now('UTC')->toIso8601String());
    }

    public static function ultimoLatido(): ?Carbon
    {
        $latido = Ajustes::obtener('programador.latido');

        return is_string($latido) ? Carbon::parse($latido) : null;
    }

    public static function cronActivo(): bool
    {
        $latido = self::ultimoLatido();

        return $latido !== null && $latido->gt(now()->subMinutes(self::MINUTOS_SIN_CRON));
    }

    /**
     * Comando de PHP y línea completa para el cron de esta instalación.
     */
    public static function comandoCron(): string
    {
        // En el sitio, PHP_BINARY puede ser php-cgi o php-fpm: para el cron hace falta el de consola.
        $php = (string) preg_replace('/-(cgi|fpm)[0-9.]*$|php-?fpm[0-9.]*$|lsphp[0-9.]*$/', 'php', PHP_BINARY);

        return 'cd '.base_path().' && '.$php.' artisan schedule:run';
    }

    /**
     * Como mucho una vez por minuto entre todas las visitas.
     */
    public static function atender(): void
    {
        try {
            if (self::cronActivo() || ! Cache::add('programador.visita', 1, 60)) {
                return;
            }
            // Corre dentro de la visita de cualquiera: los enlaces de los correos no deben salir de su cabecera Host.
            URL::forceRootUrl((string) config('app.url'));
            self::ejecutarPendientes();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<string> los comandos que se ejecutaron
     */
    public static function ejecutarPendientes(): array
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(self::SEGUNDOS_MAXIMOS + 60);
        }
        $inicio = microtime(true);
        $ultimas = (array) Ajustes::obtener('programador.visitas', []);
        $ejecutados = [];

        foreach (self::tareas() as $comando => $minutos) {
            if (microtime(true) - $inicio > self::SEGUNDOS_MAXIMOS) {
                break;
            }
            $ultima = isset($ultimas[$comando]) ? Carbon::parse((string) $ultimas[$comando]) : null;
            if ($ultima !== null && $ultima->gt(now()->subMinutes($minutos))) {
                continue;
            }
            // Se anota antes de correr: una tarea que falla no debe repetirse en cada visita.
            $ultimas[$comando] = now('UTC')->toIso8601String();
            Ajustes::guardar('programador.visitas', $ultimas);
            try {
                $partes = explode(' ', $comando);
                $opciones = [];
                foreach (array_slice($partes, 1) as $opcion) {
                    $opciones[$opcion] = true;
                }
                Artisan::call($partes[0], $opciones);
                $ejecutados[] = $comando;
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $ejecutados;
    }
}
