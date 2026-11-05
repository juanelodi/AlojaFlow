<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Caja;

use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Models\Caja;
use App\Models\RegistroAuditoria;
use App\Models\TurnoCaja;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Abrir y cerrar un turno de caja (D-080). Lo que entró se mide por **cuándo se capturó**, no por la fecha del pago: un cobro de ayer
 * anotado hoy está en el cajón de hoy. Del efectivo sale el arqueo; lo demás solo se informa.
 */
final class GestorTurnos
{
    public function abrir(Caja $caja, int $fondoInicial): TurnoCaja
    {
        if (! $caja->activa) {
            throw new TurnoRechazado(__('caja.turnos.caja_inactiva'));
        }
        if ($fondoInicial < 0) {
            throw new TurnoRechazado(__('caja.turnos.fondo_negativo'));
        }
        try {
            $turno = TurnoCaja::query()->create([
                'propiedad_id' => $caja->propiedad_id,
                'caja_id' => $caja->id,
                'caja_abierta' => $caja->id,
                'abierto_por' => auth()->id(),
                'abierto_en' => now('UTC'),
                'fondo_inicial' => $fondoInicial,
                'moneda' => $caja->propiedad->moneda,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new TurnoRechazado(__('caja.turnos.ya_abierto', ['caja' => $caja->nombre]));
            }
            throw $e;
        }
        RegistroAuditoria::anotar('turno.abrir', $turno, ['caja' => $caja->nombre, 'fondo' => $fondoInicial], $caja->propiedad_id);

        return $turno;
    }

    /**
     * @return array{entradas: int, salidas: int, esperado: int, por_metodo: array<string, int>, cobros: int, gastos: int, ingresos: int}
     */
    public function resumen(TurnoCaja $turno): array
    {
        $hasta = $turno->cerrado_en ?? now('UTC');
        $pagos = $this->dentro(DB::table('pagos'), $turno, $hasta)->groupBy('metodo')->pluck(DB::raw('SUM(importe) as total'), 'metodo')->map(fn (mixed $v): int => (int) $v)->all();
        $movimientos = $this->dentro(DB::table('movimientos'), $turno, $hasta)->groupBy('tipo', 'metodo')->get(['tipo', 'metodo', DB::raw('SUM(importe) as total')]);

        $efectivo = MetodoPago::Efectivo->value;
        $sumar = fn (TipoMovimiento $tipo, bool $soloEfectivo): int => (int) $movimientos
            ->filter(fn (object $f): bool => $f->tipo === $tipo->value && (! $soloEfectivo || $f->metodo === $efectivo))->sum('total');

        $entradas = ($pagos[$efectivo] ?? 0) + $sumar(TipoMovimiento::Ingreso, true);
        $salidas = $sumar(TipoMovimiento::Gasto, true);

        return [
            'entradas' => $entradas,
            'salidas' => $salidas,
            'esperado' => $turno->fondo_inicial + $entradas - $salidas,
            'por_metodo' => $pagos,
            'cobros' => array_sum($pagos),
            'gastos' => $sumar(TipoMovimiento::Gasto, false),
            'ingresos' => $sumar(TipoMovimiento::Ingreso, false),
        ];
    }

    public function cerrar(TurnoCaja $turno, int $contado, ?string $notas): TurnoCaja
    {
        if ($contado < 0) {
            throw new TurnoRechazado(__('caja.turnos.contado_negativo'));
        }

        return DB::transaction(function () use ($turno, $contado, $notas): TurnoCaja {
            $actual = TurnoCaja::query()->whereKey($turno->id)->lockForUpdate()->firstOrFail();
            if (! $actual->estaAbierto()) {
                throw new TurnoRechazado(__('caja.turnos.ya_cerrado'));
            }
            $actual->cerrado_en = now('UTC');
            $resumen = $this->resumen($actual);
            $actual->forceFill([
                'caja_abierta' => null,
                'cerrado_por' => auth()->id(),
                'esperado' => $resumen['esperado'],
                'contado' => $contado,
                'diferencia' => $contado - $resumen['esperado'],
                'resumen' => $resumen,
                'notas' => filled($notas) ? $notas : null,
            ])->save();
            RegistroAuditoria::anotar('turno.cerrar', $actual, ['esperado' => $resumen['esperado'], 'contado' => $contado, 'diferencia' => $contado - $resumen['esperado']], $actual->propiedad_id);

            return $actual;
        });
    }

    /**
     * La caja que se propone al registrar un cobro: la del turno que abrió quien está cobrando o, si no, la única abierta.
     */
    public static function cajaPropuesta(int $propiedadId): ?int
    {
        $abiertos = TurnoCaja::query()->where('propiedad_id', $propiedadId)->whereNotNull('caja_abierta')->get(['caja_id', 'abierto_por']);
        $mio = $abiertos->firstWhere('abierto_por', auth()->id());

        return $mio->caja_id ?? ($abiertos->count() === 1 ? $abiertos->first()?->caja_id : null);
    }

    /**
     * @template T of \Illuminate\Database\Query\Builder
     *
     * @param  T  $consulta
     * @return T
     */
    private function dentro($consulta, TurnoCaja $turno, CarbonInterface $hasta)
    {
        return $consulta->where('caja_id', $turno->caja_id)
            ->where('creado_en', '>=', $turno->abierto_en->format('Y-m-d H:i:s'))
            ->where('creado_en', '<=', $hasta->format('Y-m-d H:i:s'));
    }
}
