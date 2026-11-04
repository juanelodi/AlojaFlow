<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\TipoCupon;
use App\Models\Cupon;
use App\Models\Reserva;
use Carbon\CarbonImmutable;

/**
 * Comprueba y aplica los códigos de descuento.
 */
final class Cupones
{
    public static function buscar(int $propiedadId, ?string $codigo): ?Cupon
    {
        $codigo = trim((string) $codigo);

        return $codigo === '' ? null : Cupon::query()->where('propiedad_id', $propiedadId)->where('codigo', $codigo)->first();
    }

    /**
     * Por qué no sirve el cupón para esta estancia, o null si sirve. Las fechas valen por la llegada.
     */
    public static function motivoRechazo(Cupon $cupon, CarbonImmutable $llegada, int $noches, ?int $sinContarReserva = null): ?string
    {
        if (! $cupon->activo) {
            return __('ui.cupones.error_inactivo');
        }
        if ($cupon->valido_desde !== null && $llegada->lt($cupon->valido_desde)) {
            return __('ui.cupones.error_fechas');
        }
        if ($cupon->valido_hasta !== null && $llegada->gt($cupon->valido_hasta)) {
            return __('ui.cupones.error_fechas');
        }
        if ($noches < $cupon->estancia_minima) {
            return __('ui.cupones.error_estancia', ['noches' => $cupon->estancia_minima]);
        }
        if ($cupon->usos_maximos !== null && self::usos($cupon, $sinContarReserva) >= $cupon->usos_maximos) {
            return __('ui.cupones.error_usos');
        }

        return null;
    }

    public static function usos(Cupon $cupon, ?int $sinContarReserva = null): int
    {
        return Reserva::query()->where('cupon_id', $cupon->id)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->when($sinContarReserva !== null, fn ($q) => $q->where('id', '!=', $sinContarReserva))
            ->count();
    }

    public static function descuento(Cupon $cupon, int $alojamiento): int
    {
        $descuento = $cupon->tipo === TipoCupon::Porcentaje
            ? (int) round($alojamiento * (float) $cupon->porcentaje / 100)
            : (int) $cupon->importe;

        return min($alojamiento, max(0, $descuento));
    }
}
