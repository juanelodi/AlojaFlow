<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propietarios;

use App\Enums\EstadoReserva;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Models\CargoReserva;
use App\Models\ImpuestoReserva;
use App\Models\Movimiento;
use App\Models\Propietario;
use App\Models\Reserva;
use Carbon\CarbonImmutable;

/**
 * Cuánto le toca a un dueño de un periodo (D-115). Una estancia se reparte por noches entre los meses que toca, redondeando lo acumulado, para que los meses sumen justo lo cobrado.
 */
final class CalculadorEstado
{
    /**
     * @return array{ingresos: int, comision_portales: int, comision: int, gastos: int, cargo_fijo: int, neto: int, detalle: array<string, mixed>}
     */
    public function calcular(Propietario $propietario, CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $unidades = $propietario->unidades()->get()->keyBy('id');
        $inicio = CarbonImmutable::parse($desde->format('Y-m-d'));
        // La noche del último día también es del periodo.
        $fin = CarbonImmutable::parse($hasta->format('Y-m-d'))->addDay();
        $reservas = Reserva::query()->with(['cargos.concepto', 'impuestos'])->whereIn('unidad_id', $unidades->keys())
            // Lo que sigue sin confirmar puede vencerse: no se le promete al dueño.
            ->where('tipo', TipoReserva::Reserva->value)->where('estado', EstadoReserva::Confirmada->value)->whereNull('eliminada_en_principal_en')
            ->where('llegada', '<', $fin->format('Y-m-d'))->where('salida', '>', $inicio->format('Y-m-d'))->orderBy('llegada')->orderBy('id')->get();

        $lineas = [];
        $ingresos = 0;
        $portales = 0;
        $comision = 0;
        $ocupadas = 0;
        foreach ($reservas as $reserva) {
            $llegada = CarbonImmutable::parse($reserva->llegada->format('Y-m-d'));
            $salida = CarbonImmutable::parse($reserva->salida->format('Y-m-d'));
            $noches = max(1, (int) $llegada->diffInDays($salida));
            $antes = max(0, (int) $llegada->diffInDays($inicio, false));
            $hastaFin = min($noches, (int) $llegada->diffInDays($fin, false));
            $parte = fn (int $importe): int => (int) round($importe * $hastaFin / $noches) - (int) round($importe * $antes / $noches);

            $ingreso = $parte(max(0, $reserva->total - $this->impuestosDe($reserva) - $this->cargosDelAdministrador($reserva)));
            $portal = $parte((int) $reserva->comision);
            $unidad = $unidades->get($reserva->unidad_id);
            $porcentaje = (float) ($unidad->comision_propietario ?? $propietario->comision_porcentaje);
            $suya = (int) round(max(0, $propietario->comision_sobre_neto ? $ingreso - $portal : $ingreso) * $porcentaje / 100);

            $ingresos += $ingreso;
            $portales += $portal;
            $comision += $suya;
            $ocupadas += $hastaFin - $antes;
            // Sin el nombre del huésped: el dueño no lo necesita, y este detalle también lo ve él.
            $lineas[] = [
                'numero' => $reserva->numero,
                'unidad' => $unidad->nombre ?? '',
                'llegada' => $llegada->format('Y-m-d'),
                'salida' => $salida->format('Y-m-d'),
                'noches' => $hastaFin - $antes,
                'ingreso' => $ingreso,
                'portal' => $portal,
                'porcentaje' => $porcentaje,
                'comision' => $suya,
            ];
        }

        $estancias = Reserva::query()->where('propietario_id', $propietario->id)->where('tipo', TipoReserva::Bloqueo->value)->where('estado', '!=', EstadoReserva::Cancelada->value)
            ->where('llegada', '<', $fin->format('Y-m-d'))->where('salida', '>', $inicio->format('Y-m-d'))->orderBy('llegada')->get()
            ->map(fn (Reserva $r): array => ['unidad' => $unidades->get($r->unidad_id)->nombre ?? '', 'llegada' => $r->llegada->format('Y-m-d'), 'salida' => $r->salida->format('Y-m-d')])->all();

        $movimientos = Movimiento::query()->with('unidad')->where('propietario_id', $propietario->id)->where('tipo', TipoMovimiento::Gasto->value)
            ->where('fecha', '>=', $inicio->format('Y-m-d'))->where('fecha', '<', $fin->format('Y-m-d'))->orderBy('fecha')->orderBy('id')->get();
        $gastos = (int) $movimientos->sum('importe');
        $cargoFijo = $propietario->cargo_fijo;

        return [
            'ingresos' => $ingresos,
            'comision_portales' => $portales,
            'comision' => $comision,
            'gastos' => $gastos,
            'cargo_fijo' => $cargoFijo,
            'neto' => $ingresos - $portales - $comision - $gastos - $cargoFijo,
            'detalle' => [
                'reservas' => $lineas,
                'estancias' => $estancias,
                'gastos' => $movimientos->map(fn (Movimiento $m): array => ['id' => $m->id, 'fecha' => $m->fecha->format('Y-m-d'), 'concepto' => $m->concepto, 'unidad' => $m->unidad?->nombre, 'importe' => $m->importe, 'comprobante' => $m->comprobante_archivo_id !== null])->all(),
                'noches_ocupadas' => $ocupadas,
                'noches_posibles' => $unidades->where('activa', true)->count() * (int) $inicio->diffInDays($fin),
            ],
        ];
    }

    /**
     * Lo que se cobró de impuestos no es del dueño. Lo que cobra el portal ni siquiera está en el total.
     */
    private function impuestosDe(Reserva $reserva): int
    {
        return (int) $reserva->impuestos->filter(fn (ImpuestoReserva $i): bool => ! $i->cobrado_por_canal)->sum('importe');
    }

    private function cargosDelAdministrador(Reserva $reserva): int
    {
        return (int) $reserva->cargos->filter(fn (CargoReserva $c): bool => (bool) $c->concepto?->para_administrador)->sum('importe');
    }
}
