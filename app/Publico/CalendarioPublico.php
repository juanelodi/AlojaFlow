<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Publico;

use App\Models\Propiedad;
use App\Models\Tarifa;
use App\Models\Unidad;
use App\Motor\AjustesMotor;
use App\Motor\MotorReservas;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Qué días hay lugar, para quien todavía no tiene fechas (D-126). Solo dice si hay o no: ni cuántos, ni quién.
 */
final class CalendarioPublico
{
    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    /**
     * Las semanas de un mes, de lunes a domingo. Los huecos de antes y después son null.
     *
     * @return list<list<array{fecha: CarbonImmutable, libre: bool, reservable: bool}|null>>
     */
    public function semanas(CarbonImmutable $mes): array
    {
        $inicio = $mes->startOfMonth();
        $dias = $inicio->daysInMonth;
        $unidades = $this->unidades();
        $ocupadas = $this->ocupadas($unidades, $inicio, $inicio->addDays($dias - 1));
        $hoy = $this->propiedad->hoy();
        $desde = $hoy->addDays((new AjustesMotor($this->propiedad))->diasMinimosAntelacion())->format('Y-m-d');
        $hasta = $hoy->addDays(MotorReservas::DIAS_HORIZONTE)->format('Y-m-d');

        $celdas = array_fill(0, $inicio->dayOfWeekIso - 1, null);
        for ($i = 0; $i < $dias; $i++) {
            $fecha = $inicio->addDays($i);
            $texto = $fecha->format('Y-m-d');
            $libre = count($ocupadas[$texto] ?? []) < count($unidades);
            $celdas[] = ['fecha' => $fecha, 'libre' => $libre, 'reservable' => $libre && $texto >= $desde && $texto <= $hasta];
        }

        return array_map(fn (array $semana): array => array_pad($semana, 7, null), array_chunk($celdas, 7));
    }

    /**
     * Las que se venden en el motor: activas, en servicio y con una tarifa que se ofrece.
     *
     * @return list<int>
     */
    private function unidades(): array
    {
        $tarifas = Tarifa::query()->where('propiedad_id', $this->propiedad->id)->where('activa', true)->where('visible_motor', true)->pluck('id')->all();

        return Unidad::query()->with('otrasTarifas:id')->where('propiedad_id', $this->propiedad->id)->where('activa', true)
            ->whereDoesntHave('estado', fn ($q) => $q->where('fuera_de_servicio', true))->get()
            ->filter(fn (Unidad $unidad): bool => array_intersect($unidad->tarifasIds(), $tarifas) !== [])
            ->map(fn (Unidad $unidad): int => $unidad->id)->values()->all();
    }

    /**
     * Por día, las que no se pueden vender: con reserva o con un bloqueo de otro calendario.
     *
     * @param  list<int>  $unidades
     * @return array<string, array<int, true>>
     */
    private function ocupadas(array $unidades, CarbonImmutable $inicio, CarbonImmutable $fin): array
    {
        $desde = $inicio->format('Y-m-d');
        $hasta = $fin->format('Y-m-d');
        $ocupadas = [];
        foreach (DB::table('reservas_noches')->whereIn('unidad_id', $unidades)->whereBetween('fecha', [$desde, $hasta])->get(['fecha', 'unidad_id']) as $noche) {
            $ocupadas[substr((string) $noche->fecha, 0, 10)][(int) $noche->unidad_id] = true;
        }
        foreach (DB::table('bloqueos_externos')->whereIn('unidad_id', $unidades)->whereNull('eliminado_en')->where('llegada', '<=', $hasta)->where('salida', '>', $desde)->get(['unidad_id', 'llegada', 'salida']) as $bloqueo) {
            $dia = CarbonImmutable::parse(max($desde, substr((string) $bloqueo->llegada, 0, 10)));
            $salida = substr((string) $bloqueo->salida, 0, 10);
            for (; $dia->format('Y-m-d') < $salida && $dia->format('Y-m-d') <= $hasta; $dia = $dia->addDay()) {
                $ocupadas[$dia->format('Y-m-d')][(int) $bloqueo->unidad_id] = true;
            }
        }

        return $ocupadas;
    }
}
