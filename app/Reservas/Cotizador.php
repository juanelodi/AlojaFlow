<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\BaseCargo;
use App\Enums\ModoTarifa;
use App\Models\ConceptoCargo;
use App\Models\Cupon;
use App\Models\Impuesto;
use App\Models\Tarifa;
use App\Models\TemporadaTarifa;
use App\Models\Unidad;
use Carbon\CarbonImmutable;

/**
 * Calcula precios (D-036, D-041). Cada tarifa elige si cobra por noche con persona extra o por
 * persona. Cada noche usa el precio de la temporada que la cubre o, si no hay, el de la tarifa; las
 * noches de viernes y sábado usan el de fin de semana si existe. Si dos temporadas se pisan, vale la
 * que empieza más tarde.
 */
final class Cotizador
{
    public static function cotizar(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas = 1): ?Cotizacion
    {
        $tarifa = $unidad->tarifa_id === null ? null : Tarifa::query()->where('activa', true)->find($unidad->tarifa_id);
        // El recorrido es por noche: sin este tope, unas fechas absurdas ocupan el servidor.
        if ($tarifa === null || $salida->lte($llegada) || $llegada->diffInDays($salida) > GestorReservas::NOCHES_MAXIMAS) {
            return null;
        }

        $temporadas = TemporadaTarifa::query()->where('tarifa_id', $tarifa->id)
            ->where('desde', '<', $salida->format('Y-m-d'))
            ->where('hasta', '>=', $llegada->format('Y-m-d'))
            ->orderByDesc('desde')->orderByDesc('id')
            ->get();
        $personas = max(1, $personas);

        $noches = [];
        $estanciaMinima = $tarifa->estancia_minima;
        for ($dia = $llegada; $dia->lt($salida); $dia = $dia->addDay()) {
            $temporada = $temporadas->first(fn (TemporadaTarifa $t): bool => $t->desde->lte($dia) && $t->hasta->gte($dia));
            if ($dia->equalTo($llegada) && $temporada?->estancia_minima !== null) {
                $estanciaMinima = max($estanciaMinima, $temporada->estancia_minima);
            }
            $noches[] = self::precioNoche($tarifa, $temporada, $dia, $personas);
        }

        return new Cotizacion($tarifa->nombre, array_sum($noches), $noches, max(1, $estanciaMinima));
    }

    /**
     * El desglose completo: alojamiento, cupón, cargos obligatorios o elegidos e impuestos.
     *
     * @param  array<int, float>  $cargosElegidos  id del concepto => cantidad
     */
    public static function presupuesto(Unidad $unidad, CarbonImmutable $llegada, CarbonImmutable $salida, int $personas, array $cargosElegidos = [], ?Cupon $cupon = null, int $descuentoManual = 0): Presupuesto
    {
        $cotizacion = self::cotizar($unidad, $llegada, $salida, $personas);
        $alojamiento = $cotizacion->total ?? 0;
        $noches = max(0, (int) $llegada->diffInDays($salida));
        $personas = max(1, $personas);

        $descuento = $descuentoManual;
        if ($cupon !== null) {
            $descuento += Cupones::descuento($cupon, $alojamiento);
        }
        $descuento = min($descuento, $alojamiento);

        $cargos = [];
        $conceptos = ConceptoCargo::query()->where('propiedad_id', $unidad->propiedad_id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get();
        foreach ($conceptos as $concepto) {
            if (! $concepto->obligatorio && ! isset($cargosElegidos[$concepto->id])) {
                continue;
            }
            $cantidad = (float) ($cargosElegidos[$concepto->id] ?? 1);
            $cargos[] = [
                'concepto_cargo_id' => $concepto->id,
                'nombre' => $concepto->nombre,
                'cantidad' => $cantidad,
                'importe' => (int) round($cantidad * self::importeSegunBase($concepto->base, $concepto->importe, $concepto->porcentaje, $alojamiento - $descuento, $noches, $personas)),
            ];
        }

        $base = $alojamiento - $descuento;
        $impuestos = [];
        foreach (Impuesto::query()->where('propiedad_id', $unidad->propiedad_id)->where('activo', true)->orderBy('orden')->orderBy('nombre')->get() as $impuesto) {
            $impuestos[] = [
                'nombre' => $impuesto->nombre,
                'importe' => self::importeImpuesto($impuesto, $base, $noches, $personas),
                'incluido' => $impuesto->incluido_en_precio,
            ];
        }

        return new Presupuesto($cotizacion, $alojamiento, $descuento, $cargos, $impuestos);
    }

    private static function precioNoche(Tarifa $tarifa, ?TemporadaTarifa $temporada, CarbonImmutable $dia, int $personas): int
    {
        $base = $temporada->precio_noche ?? $tarifa->precio_noche;
        // Viernes y sábado: son las noches que se venden como fin de semana.
        if (in_array($dia->dayOfWeek, [5, 6], true)) {
            $base = $temporada !== null
                ? ($temporada->precio_fin_semana ?? $temporada->precio_noche)
                : ($tarifa->precio_fin_semana ?? $tarifa->precio_noche);
        }
        if ($tarifa->modo === ModoTarifa::PorPersona) {
            return $base * $personas;
        }
        $extra = $temporada->precio_persona_extra ?? $tarifa->precio_persona_extra;

        return $base + max(0, $personas - $tarifa->personas_incluidas) * $extra;
    }

    private static function importeSegunBase(BaseCargo $base, int $importe, ?string $porcentaje, int $alojamiento, int $noches, int $personas): float
    {
        return match ($base) {
            BaseCargo::PorEstancia => $importe,
            BaseCargo::PorNoche => $importe * $noches,
            BaseCargo::PorPersona => $importe * $personas,
            BaseCargo::PorPersonaNoche => $importe * $personas * $noches,
            BaseCargo::PorcentajeAlojamiento => $alojamiento * (float) $porcentaje / 100,
        };
    }

    /**
     * Si el impuesto va incluido en un precio porcentual, se saca del precio («hacia atrás»): del
     * total con 19 % incluido, el impuesto es total − total / 1.19.
     */
    private static function importeImpuesto(Impuesto $impuesto, int $base, int $noches, int $personas): int
    {
        if ($impuesto->base === BaseCargo::PorcentajeAlojamiento) {
            $tasa = (float) $impuesto->tasa / 100;

            return (int) round($impuesto->incluido_en_precio ? $base - $base / (1 + $tasa) : $base * $tasa);
        }

        return (int) round(self::importeSegunBase($impuesto->base, (int) $impuesto->importe, null, $base, $noches, $personas));
    }
}
