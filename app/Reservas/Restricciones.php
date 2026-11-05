<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Models\Restriccion;
use App\Models\Unidad;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que dicen las restricciones de una estancia (D-084). El motor las aplica, porque ahí nadie puede decidir; al personal solo se le avisa,
 * porque un caso especial lo resuelve una persona. El cierre de llegada y los días de entrada miran la llegada; el cierre de salida, la salida.
 */
final class Restricciones
{
    /**
     * Lo que no se cumple en esa unidad, ya traducido. Vacío si se puede vender.
     *
     * @return list<string>
     */
    public static function deUnidad(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, CarbonImmutable $hoy): array
    {
        return self::incumplidas($unidad->propiedad_id, $unidad, $llegada, $salida, $hoy);
    }

    /**
     * Lo que vale para toda la propiedad: para decirle al huésped por qué no hay nada en esas fechas.
     *
     * @return list<string>
     */
    public static function deLaPropiedad(int $propiedadId, CarbonImmutable $llegada, CarbonImmutable $salida, CarbonImmutable $hoy): array
    {
        return self::incumplidas($propiedadId, null, $llegada, $salida, $hoy);
    }

    /**
     * @return list<string>
     */
    private static function incumplidas(int $propiedadId, ?Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, CarbonImmutable $hoy): array
    {
        $incumplidas = [];
        foreach (self::vigentes($propiedadId, $unidad?->id, $llegada, $salida) as $r) {
            $cubreLlegada = self::cubre($r, $llegada);
            if ($r->cerrado_llegada && $cubreLlegada) {
                $incumplidas[] = __('operacion.restricciones.error_llegada', ['fecha' => Formato::fecha($llegada)]);
            }
            if ($r->cerrado_salida && self::cubre($r, $salida)) {
                $incumplidas[] = __('operacion.restricciones.error_salida', ['fecha' => Formato::fecha($salida)]);
            }
            if ($r->dias_llegada !== null && $r->dias_llegada > 0 && $cubreLlegada && ($r->dias_llegada & (1 << ($llegada->dayOfWeekIso - 1))) === 0) {
                $incumplidas[] = __('operacion.restricciones.error_dia', ['dias' => self::nombresDias($r->dias_llegada)]);
            }
            if ($r->estancia_maxima !== null && $cubreLlegada && $llegada->diffInDays($salida) > $r->estancia_maxima) {
                $incumplidas[] = __('operacion.restricciones.error_maxima', ['noches' => $r->estancia_maxima]);
            }
            if ($r->anticipacion_maxima !== null && $cubreLlegada && $hoy->diffInDays($llegada) > $r->anticipacion_maxima) {
                $incumplidas[] = __('operacion.restricciones.error_anticipacion', ['dias' => $r->anticipacion_maxima]);
            }
            if ($unidad !== null && $r->noches_preparacion !== null && $r->noches_preparacion > 0 && $cubreLlegada && self::chocaConPreparacion($unidad, $llegada, $salida, $r->noches_preparacion)) {
                $incumplidas[] = __('operacion.restricciones.error_preparacion', ['noches' => $r->noches_preparacion]);
            }
        }

        return array_values(array_unique($incumplidas));
    }

    /**
     * Las que tocan esas fechas: de toda la propiedad o de esa unidad. Con la salida, porque se puede cerrar el día en que termina la estancia.
     *
     * @return Collection<int, Restriccion>
     */
    private static function vigentes(int $propiedadId, ?int $unidadId, CarbonImmutable $llegada, CarbonImmutable $salida): Collection
    {
        return Restriccion::query()->where('propiedad_id', $propiedadId)->where('activa', true)
            ->where(fn ($q) => $q->whereNull('unidad_id')->orWhere('unidad_id', $unidadId))
            ->where('desde', '<=', $salida->format('Y-m-d'))->where('hasta', '>=', $llegada->format('Y-m-d'))
            ->get();
    }

    private static function cubre(Restriccion $r, CarbonImmutable $fecha): bool
    {
        return $r->desde->format('Y-m-d') <= $fecha->format('Y-m-d') && $r->hasta->format('Y-m-d') >= $fecha->format('Y-m-d');
    }

    /**
     * Las noches antes de llegar y después de salir tienen que estar libres, para preparar la unidad.
     */
    private static function chocaConPreparacion(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $noches): bool
    {
        return DB::table('reservas_noches')->where('unidad_id', $unidad->id)
            ->where(fn ($q) => $q->whereBetween('fecha', [$llegada->subDays($noches)->format('Y-m-d'), $llegada->subDay()->format('Y-m-d')])
                ->orWhereBetween('fecha', [$salida->format('Y-m-d'), $salida->addDays($noches - 1)->format('Y-m-d')]))
            ->exists();
    }

    private static function nombresDias(int $mascara): string
    {
        $nombres = [];
        for ($i = 0; $i < 7; $i++) {
            if (($mascara & (1 << $i)) !== 0) {
                $nombres[] = CarbonImmutable::create(2024, 1, 1 + $i)?->locale(app()->getLocale())->isoFormat('dddd');
            }
        }

        return implode(', ', array_filter($nombres));
    }
}
