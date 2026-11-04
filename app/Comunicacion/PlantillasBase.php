<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Comunicacion;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoReserva;
use App\Models\Automatizacion;
use App\Models\Plantilla;
use App\Models\Propiedad;
use Illuminate\Support\Facades\Lang;

/**
 * Los mensajes de partida, para no empezar de una hoja en blanco.
 */
final class PlantillasBase
{
    private const CODIGOS = ['confirmacion', 'solicitud', 'recordatorio', 'gracias', 'cancelacion'];

    public static function sembrar(Propiedad $propiedad): int
    {
        $creadas = 0;
        foreach (self::CODIGOS as $codigo) {
            $asunto = [];
            $cuerpo = [];
            foreach ((array) config('alojaflow.idiomas') as $idioma) {
                $clave = 'mensajes.base.'.$codigo;
                if (Lang::has($clave.'.cuerpo', (string) $idioma, false)) {
                    $asunto[$idioma] = __($clave.'.asunto', [], (string) $idioma);
                    $cuerpo[$idioma] = __($clave.'.cuerpo', [], (string) $idioma);
                }
            }
            $plantilla = Plantilla::query()->firstOrCreate(
                ['propiedad_id' => $propiedad->id, 'codigo' => $codigo],
                ['nombre' => __('mensajes.base.'.$codigo.'.nombre'), 'asunto' => $asunto, 'cuerpo' => $cuerpo],
            );
            $creadas += $plantilla->wasRecentlyCreated ? 1 : 0;
        }

        return $creadas;
    }

    /**
     * Las automatizaciones de ejemplo de la demostración: confirmar, recordar y agradecer.
     */
    public static function automatizacionesDemo(Propiedad $propiedad): void
    {
        $plantillas = Plantilla::query()->where('propiedad_id', $propiedad->id)->pluck('id', 'codigo');
        $reglas = [
            ['confirmacion', DisparadorAutomatizacion::ReservaConfirmada, 0, null, null],
            ['recordatorio', DisparadorAutomatizacion::AntesLlegada, 2, '10:00', ['estado' => EstadoReserva::Confirmada->value]],
            ['gracias', DisparadorAutomatizacion::DespuesSalida, 1, '11:00', null],
        ];
        foreach ($reglas as [$codigo, $disparador, $dias, $hora, $condiciones]) {
            Automatizacion::query()->create([
                'propiedad_id' => $propiedad->id,
                'nombre' => __('mensajes.base.'.$codigo.'.nombre'),
                'disparador' => $disparador,
                'dias' => $dias,
                'hora' => $hora,
                'condiciones' => $condiciones,
                'accion' => AccionAutomatizacion::CorreoHuesped,
                'plantilla_id' => $plantillas[$codigo],
            ]);
        }
    }
}
