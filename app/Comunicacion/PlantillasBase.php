<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Models\Automatizacion;
use App\Models\Plantilla;
use App\Models\Propiedad;
use Illuminate\Support\Facades\Lang;

/**
 * Los mensajes de partida, para no empezar de una hoja en blanco.
 */
final class PlantillasBase
{
    private const CODIGOS = ['confirmacion', 'solicitud', 'instrucciones', 'recordatorio', 'pago_pendiente', 'pago_recibido', 'cambio', 'antes_salida', 'gracias', 'pide_opinion', 'cancelacion'];

    /**
     * Los envíos que casi todo alojamiento quiere: confirmar, recordar y agradecer. Nunca se prenden solos.
     *
     * @var list<array{0: string, 1: DisparadorAutomatizacion, 2: int, 3: string|null, 4: array<string, string>|null}>
     */
    private const RECOMENDADOS = [
        ['confirmacion', DisparadorAutomatizacion::ReservaConfirmada, 0, null, null],
        ['recordatorio', DisparadorAutomatizacion::AntesLlegada, 2, '10:00', ['estado' => 'confirmada']],
        ['gracias', DisparadorAutomatizacion::DespuesSalida, 1, '11:00', null],
    ];

    private const RECORDATORIOS_CUOTA = [
        'cuota_por_vencer' => [DisparadorAutomatizacion::AntesCuota, 3],
        'cuota_vencida' => [DisparadorAutomatizacion::CuotaVencida, 1],
    ];

    /**
     * @param  list<string>|null  $solo  los códigos a crear; sin decir, todos
     */
    public static function sembrar(Propiedad $propiedad, ?array $solo = null): int
    {
        $creadas = 0;
        foreach ($solo === null ? self::CODIGOS : array_intersect(self::CODIGOS, $solo) as $codigo) {
            $creadas += self::plantilla($propiedad, $codigo, 'mensajes.base.'.$codigo)->wasRecentlyCreated ? 1 : 0;
        }

        return $creadas;
    }

    /**
     * Los dos recordatorios del calendario de cobros, listos con un clic. Se pueden cambiar en Automatizaciones, y prenderlos otra vez respeta lo cambiado.
     */
    public static function prenderRecordatoriosDeCuota(Propiedad $propiedad): void
    {
        foreach (self::RECORDATORIOS_CUOTA as $codigo => [$disparador, $dias]) {
            $plantilla = self::plantilla($propiedad, $codigo, 'estancias.mensajes.'.$codigo);
            $automatizacion = Automatizacion::query()->firstOrCreate(['propiedad_id' => $propiedad->id, 'disparador' => $disparador->value], [
                'nombre' => $plantilla->nombre,
                'dias' => $dias,
                'hora' => '10:00',
                'condiciones' => ['saldo' => 'con_saldo'],
                'accion' => AccionAutomatizacion::CorreoHuesped,
                'plantilla_id' => $plantilla->id,
            ]);
            $automatizacion->update(['activa' => true]);
        }
    }

    public static function recordatoriosDeCuotaPrendidos(Propiedad $propiedad): bool
    {
        return Automatizacion::query()->where('propiedad_id', $propiedad->id)->where('activa', true)
            ->whereIn('disparador', [DisparadorAutomatizacion::AntesCuota->value, DisparadorAutomatizacion::CuotaVencida->value])->exists();
    }

    private static function plantilla(Propiedad $propiedad, string $codigo, string $clave): Plantilla
    {
        $asunto = [];
        $cuerpo = [];
        foreach ((array) config('alojaflow.idiomas') as $idioma) {
            if (Lang::has($clave.'.cuerpo', (string) $idioma, false)) {
                $asunto[$idioma] = __($clave.'.asunto', [], (string) $idioma);
                $cuerpo[$idioma] = __($clave.'.cuerpo', [], (string) $idioma);
            }
        }

        return Plantilla::query()->firstOrCreate(
            ['propiedad_id' => $propiedad->id, 'codigo' => $codigo],
            ['nombre' => __($clave.'.nombre', [], $propiedad->idioma), 'asunto' => $asunto, 'cuerpo' => $cuerpo],
        );
    }

    /**
     * Prende los envíos recomendados. Lo ya creado se queda como esté, incluso apagado a propósito.
     *
     * @return int cuántos creó
     */
    public static function prenderRecomendados(Propiedad $propiedad): int
    {
        self::sembrar($propiedad);
        $plantillas = Plantilla::query()->where('propiedad_id', $propiedad->id)->pluck('id', 'codigo');
        $creados = 0;
        foreach (self::RECOMENDADOS as [$codigo, $disparador, $dias, $hora, $condiciones]) {
            $automatizacion = Automatizacion::query()->firstOrCreate(
                // Por disparador: si ya manda algo en ese momento, otro sería un correo doble.
                ['propiedad_id' => $propiedad->id, 'disparador' => $disparador->value],
                ['plantilla_id' => $plantillas[$codigo], 'nombre' => __('mensajes.base.'.$codigo.'.nombre', [], $propiedad->idioma), 'dias' => $dias, 'hora' => $hora, 'condiciones' => $condiciones, 'accion' => AccionAutomatizacion::CorreoHuesped],
            );
            $creados += $automatizacion->wasRecentlyCreated ? 1 : 0;
        }

        return $creados;
    }

    /**
     * Las automatizaciones de ejemplo de la demostración.
     */
    public static function automatizacionesDemo(Propiedad $propiedad): void
    {
        self::prenderRecomendados($propiedad);
    }
}
