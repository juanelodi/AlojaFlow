<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\BaseCargoCanal;
use App\Enums\CalculoCargoCanal;
use App\Enums\ClaseCargoCanal;
use App\Paises\Pais;

/**
 * Lo que suele cobrar cada portal (D-149), para no empezar en blanco. Son puntos de partida de fuentes públicas: el contrato de cada alojamiento manda,
 * y lo que varía mucho o no se pudo comprobar llega apagado o sin valor.
 */
final class CargosSugeridos
{
    /** @return array<string, string> */
    public static function plataformas(): array
    {
        return ['booking' => 'Booking.com', 'expedia' => 'Expedia', 'airbnb' => 'Airbnb'];
    }

    /**
     * @return array{comision: ?float, comision_base: string, cargos: list<array{nombre: string, clase: string, calculo: string, valor: float, base: string, activo: bool}>}
     */
    public static function de(string $plataforma, Pais $pais): array
    {
        $delPais = $pais->cargosDeCanales();
        [$comision, $base, $propios] = match ($plataforma) {
            // Sobre el total de la reserva, limpieza incluida. El cargo por cobrar es solo si cobra el portal.
            'booking' => [15.0, BaseCargoCanal::TotalFinal, [
                ['pago_booking', ClaseCargoCanal::ProcesamientoPago, 1.5, BaseCargoCanal::TotalFinal, false],
                ['visibilidad_booking', ClaseCargoCanal::Promocion, 0.0, BaseCargoCanal::TotalFinal, false],
            ]],
            // Va por contrato: no hay una cifra pública que sugerir.
            'expedia' => [null, BaseCargoCanal::SinImpuestos, [
                ['pago_expedia', ClaseCargoCanal::ProcesamientoPago, 0.0, BaseCargoCanal::TotalFinal, false],
                ['visibilidad_expedia', ClaseCargoCanal::Promocion, 0.0, BaseCargoCanal::SinImpuestos, false],
            ]],
            // Tarifa única para el anfitrión.
            'airbnb' => [15.5, BaseCargoCanal::SinImpuestos, []],
            default => [null, BaseCargoCanal::TotalFinal, []],
        };
        $cargos = array_map(fn (array $c): array => self::fila($c[0], $c[1], $c[2], $c[3], $c[4]), $propios);
        foreach ($delPais['cargos'] as $c) {
            $cargos[] = self::fila($c['clave'], $c['clase'], $c['valor'], $c['base'], true);
        }

        return ['comision' => $delPais['comisiones'][$plataforma] ?? $comision, 'comision_base' => $base->value, 'cargos' => $cargos];
    }

    /**
     * @return array{nombre: string, clase: string, calculo: string, valor: float, base: string, activo: bool}
     */
    private static function fila(string $clave, ClaseCargoCanal $clase, float $valor, BaseCargoCanal $base, bool $activo): array
    {
        return ['nombre' => __('canales.sugeridos.'.$clave), 'clase' => $clase->value, 'calculo' => CalculoCargoCanal::Porcentaje->value, 'valor' => $valor, 'base' => $base->value, 'activo' => $activo];
    }
}
