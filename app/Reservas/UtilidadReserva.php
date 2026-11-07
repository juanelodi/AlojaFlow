<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\BaseCargoCanal;
use App\Enums\CalculoCargoCanal;
use App\Enums\ClaseCargoCanal;
use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\Canal;
use App\Models\CanalCargo;
use App\Models\Reserva;
use App\Support\Dinero;

/**
 * Cuánto le queda al alojamiento de una reserva (D-149).
 *
 * Bruta: lo que paga el huésped, menos los impuestos que cobra por cuenta de otro, menos comisión y cargos del canal.
 * Neta: la bruta menos lo que el canal retiene. Se calcula con los cargos de hoy del canal; la comisión es la guardada en la reserva o, sin ella, la del canal.
 */
final class UtilidadReserva
{
    /** @var array<string, ?Canal> */
    private array $canales = [];

    /**
     * @return array{ingreso: int, impuestos: int, comision: int, cargos: list<array{nombre: string, importe: int}>, total_cargos: int, retenciones: list<array{nombre: string, importe: int}>, total_retenciones: int, bruta: int, neta: int, canal: ?string}|null null si no hay venta: un bloqueo o una cancelada
     */
    public function de(Reserva $reserva): ?array
    {
        if ($reserva->tipo !== TipoReserva::Reserva || $reserva->estado === EstadoReserva::Cancelada) {
            return null;
        }
        $impuestos = self::impuestos($reserva);
        $sinImpuestos = max(0, $reserva->total - $impuestos);
        $tarifaBase = max(0, $sinImpuestos - (int) $reserva->cargos()->sum('importe'));
        $noches = max(0, (int) $reserva->llegada->diffInDays($reserva->salida));
        $canal = $this->canal($reserva);
        $bases = [BaseCargoCanal::TarifaBase->value => $tarifaBase, BaseCargoCanal::SinImpuestos->value => $sinImpuestos, BaseCargoCanal::TotalFinal->value => $reserva->total];

        // Sin comisión anotada, la del canal: una reserva anterior a configurarlo o que no pasó por el formulario también la paga.
        $comision = $reserva->comision;
        if ($comision === 0 && (float) $canal?->comision_porcentaje > 0) {
            $comision = (int) round(($bases[$canal->comision_base->value] ?? $reserva->total) * (float) $canal->comision_porcentaje / 100);
        }

        $filas = $canal === null ? collect() : $canal->cargos->where('activo', true);
        $cargos = [];
        $retenciones = [];
        $comisiones = $comision;
        // Primero lo que cobra el canal, porque el impuesto sobre sus cargos se calcula de eso.
        foreach ($filas->reject(fn (CanalCargo $c): bool => $c->clase === ClaseCargoCanal::ImpuestoSobreCargos || $c->base === BaseCargoCanal::Comisiones) as $cargo) {
            $importe = self::importe($cargo, $bases[$cargo->base->value], $noches, $reserva->moneda);
            if ($cargo->esRetencion()) {
                $retenciones[] = ['nombre' => $cargo->nombre, 'importe' => $importe];
            } else {
                $cargos[] = ['nombre' => $cargo->nombre, 'importe' => $importe];
                $comisiones += $importe;
            }
        }
        foreach ($filas->filter(fn (CanalCargo $c): bool => $c->clase === ClaseCargoCanal::ImpuestoSobreCargos || $c->base === BaseCargoCanal::Comisiones) as $cargo) {
            $base = $cargo->base === BaseCargoCanal::Comisiones ? $comisiones : $bases[$cargo->base->value];
            $importe = self::importe($cargo, $base, $noches, $reserva->moneda);
            if ($cargo->esRetencion()) {
                $retenciones[] = ['nombre' => $cargo->nombre, 'importe' => $importe];
            } else {
                $cargos[] = ['nombre' => $cargo->nombre, 'importe' => $importe];
            }
        }
        $totalCargos = (int) array_sum(array_column($cargos, 'importe'));
        $totalRetenciones = (int) array_sum(array_column($retenciones, 'importe'));
        $bruta = $reserva->total - $impuestos - $comision - $totalCargos;

        return [
            'ingreso' => $reserva->total,
            'impuestos' => $impuestos,
            'comision' => $comision,
            'cargos' => $cargos,
            'total_cargos' => $totalCargos,
            'retenciones' => $retenciones,
            'total_retenciones' => $totalRetenciones,
            'bruta' => $bruta,
            'neta' => $bruta - $totalRetenciones,
            'canal' => $canal?->nombre,
        ];
    }

    /**
     * Los que se guardaron al vender, sin los que cobró el canal. De una copia de la principal solo se conoce su porcentaje, incluido en el total.
     */
    private static function impuestos(Reserva $reserva): int
    {
        $porcentaje = (float) $reserva->getAttribute('impuestos_porcentaje');
        if ($porcentaje > 0) {
            return (int) round($reserva->total - $reserva->total / (1 + $porcentaje / 100));
        }

        return (int) $reserva->impuestos()->where('cobrado_por_canal', false)->sum('importe');
    }

    private static function importe(CanalCargo $cargo, int $base, int $noches, string $moneda): int
    {
        return match ($cargo->calculo) {
            CalculoCargoCanal::Porcentaje => (int) round($base * (float) $cargo->valor / 100),
            CalculoCargoCanal::FijoReserva => Dinero::aUnidadMenor((string) (float) $cargo->valor, $moneda),
            CalculoCargoCanal::FijoNoche => $noches * Dinero::aUnidadMenor((string) (float) $cargo->valor, $moneda),
        };
    }

    private function canal(Reserva $reserva): ?Canal
    {
        if (blank($reserva->origen_texto)) {
            return null;
        }
        $clave = $reserva->propiedad_id.'|'.$reserva->origen_texto;
        if (! array_key_exists($clave, $this->canales)) {
            $this->canales[$clave] = Canal::query()->with('cargos')->where('propiedad_id', $reserva->propiedad_id)->where('nombre', $reserva->origen_texto)->first();
        }

        return $this->canales[$clave];
    }
}
