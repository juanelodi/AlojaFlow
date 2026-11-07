<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\RegistroAuditoria;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Poner un precio a unas fechas desde el calendario de tarifas (D-148). Es una temporada más; las que se encimen se recortan,
 * para que el día diga el precio que se acaba de escribir, sea cual sea el orden en que se crearon.
 */
final class GestorPreciosTarifa
{
    /**
     * @param  CarbonImmutable  $hasta  última noche, inclusive
     */
    public function fijar(Tarifa $tarifa, CarbonImmutable $desde, CarbonImmutable $hasta, string $nombre, int $precioNoche, ?int $precioFinSemana = null, ?int $estanciaMinima = null): TemporadaTarifa
    {
        if ($tarifa->tarifa_base_id !== null) {
            throw new ReservaRechazada(__('tarifas_calendario.error_derivada', ['tarifa' => (string) $tarifa->base?->nombre]));
        }
        if ($hasta->lt($desde) || $precioNoche < 0) {
            throw new ReservaRechazada(__('ui.reservas.error_fechas'));
        }

        return DB::transaction(function () use ($tarifa, $desde, $hasta, $nombre, $precioNoche, $precioFinSemana, $estanciaMinima): TemporadaTarifa {
            $encimadas = TemporadaTarifa::query()->where('tarifa_id', $tarifa->id)->where('desde', '<=', $hasta->format('Y-m-d'))->where('hasta', '>=', $desde->format('Y-m-d'))->lockForUpdate()->get();
            foreach ($encimadas as $temporada) {
                $this->recortar($temporada, $desde, $hasta);
            }
            $nueva = TemporadaTarifa::query()->create([
                'tarifa_id' => $tarifa->id,
                'nombre' => $nombre,
                'desde' => $desde->format('Y-m-d'),
                'hasta' => $hasta->format('Y-m-d'),
                'precio_noche' => $precioNoche,
                'precio_fin_semana' => $precioFinSemana,
                'estancia_minima' => $estanciaMinima,
            ]);
            RegistroAuditoria::anotar('tarifa.precio_fechas', $tarifa, ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d'), 'precio' => $precioNoche], $tarifa->propiedad_id);

            return $nueva;
        });
    }

    private function recortar(TemporadaTarifa $temporada, CarbonImmutable $desde, CarbonImmutable $hasta): void
    {
        $empiezaAntes = $temporada->desde->format('Y-m-d') < $desde->format('Y-m-d');
        $terminaDespues = $temporada->hasta->format('Y-m-d') > $hasta->format('Y-m-d');
        if (! $empiezaAntes && ! $terminaDespues) {
            $temporada->delete();

            return;
        }
        // Un precio por la temporada completa ya no vale si dejó de ser la misma temporada.
        $temporada->precio_completa = null;
        if ($empiezaAntes && $terminaDespues) {
            $resto = $temporada->replicate();
            $resto->desde = $hasta->addDay();
            $resto->save();
        }
        if ($empiezaAntes) {
            $temporada->hasta = $desde->subDay();
        } else {
            $temporada->desde = $hasta->addDay();
        }
        $temporada->save();
    }
}
