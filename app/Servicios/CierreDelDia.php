<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Servicios;

use App\Enums\EstadoReserva;
use App\Enums\LugarOmision;
use App\Enums\TipoMovimiento;
use App\Enums\TipoReserva;
use App\Limpieza\Omisiones;
use App\Models\CierreDia;
use App\Models\Propiedad;
use App\Models\RegistroAuditoria;
use App\Models\TurnoCaja;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lo que dejó un día: quién no llegó, quién no salió, lo que entró a la caja. Cerrar guarda la foto;
 * no impide corregir después, solo deja constancia de cómo estaba.
 */
final class CierreDelDia
{
    /**
     * @return array{llegadas_sin_registrar: int, salidas_sin_registrar: int, por_cobrar_salidas: int, ocupadas: int, unidades: int, cobros: array<string, int>, gastos: int, ingresos: int}
     */
    public function resumen(Propiedad $propiedad, CarbonImmutable $fecha): array
    {
        $dia = $fecha->format('Y-m-d');
        $omitidos = Omisiones::huespedes([$propiedad->id], LugarOmision::Hoy);
        $reservas = fn () => Omisiones::sinEstos(DB::table('reservas'), 'titular_id', $omitidos)->where('propiedad_id', $propiedad->id)->where('tipo', TipoReserva::Reserva->value)
            ->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en');
        $movimientos = fn (TipoMovimiento $tipo): int => (int) DB::table('movimientos')->where('propiedad_id', $propiedad->id)->where('fecha', $dia)->where('tipo', $tipo->value)->sum('importe');

        return [
            'llegadas_sin_registrar' => $reservas()->where('llegada', $dia)->whereNull('entrada_registrada_en')->count(),
            'salidas_sin_registrar' => $reservas()->where('salida', $dia)->whereNull('salida_registrada_en')->count(),
            'por_cobrar_salidas' => (int) $reservas()->where('salida', $dia)->sum('pendiente'),
            'ocupadas' => DB::table('reservas_noches')->where('propiedad_id', $propiedad->id)->where('fecha', $dia)->distinct()->count('unidad_id'),
            'unidades' => DB::table('unidades')->where('propiedad_id', $propiedad->id)->where('activa', true)->count(),
            'cobros' => DB::table('pagos')->where('propiedad_id', $propiedad->id)->where('fecha', $dia)->groupBy('metodo')->pluck(DB::raw('SUM(importe) as total'), 'metodo')->map(fn (mixed $v): int => (int) $v)->all(),
            'gastos' => $movimientos(TipoMovimiento::Gasto),
            'ingresos' => $movimientos(TipoMovimiento::Ingreso),
            'turnos' => $this->turnos($propiedad, $fecha),
            'sin_confirmar' => $reservas()->where('estado', EstadoReserva::SinConfirmar->value)->where('llegada', '>', $dia)->where('llegada', '<=', $fecha->addDays(3)->format('Y-m-d'))->count(),
            // Solo el número: el cierre se guarda, y no hace falta guardar con él el nombre de nadie.
            'detalle' => [
                'llegadas_sin_registrar' => $this->numeros($reservas()->where('llegada', $dia)->whereNull('entrada_registrada_en')),
                'salidas_sin_registrar' => $this->numeros($reservas()->where('salida', $dia)->whereNull('salida_registrada_en')),
                'salidas_con_saldo' => $this->numeros($reservas()->where('salida', $dia)->where('pendiente', '>', 0)),
                'sin_confirmar' => $this->numeros($reservas()->where('estado', EstadoReserva::SinConfirmar->value)->where('llegada', '>', $dia)->where('llegada', '<=', $fecha->addDays(3)->format('Y-m-d'))),
            ],
            'caja' => $this->caja($propiedad, $dia),
        ];
    }

    /**
     * @return list<array{id: int, numero: string}>
     */
    private function numeros(Builder $consulta): array
    {
        return $consulta->orderBy('llegada')->orderBy('id')->limit(50)->get(['id', 'numero'])
            ->map(fn (object $r): array => ['id' => (int) $r->id, 'numero' => (string) $r->numero])->all();
    }

    /**
     * Lo que entró y salió ese día, por caja y método.
     *
     * @return list<array{caja: string|null, metodo: string, entradas: int, salidas: int}>
     */
    private function caja(Propiedad $propiedad, string $dia): array
    {
        $nombres = DB::table('cajas')->where('propiedad_id', $propiedad->id)->pluck('nombre', 'id');
        $filas = [];
        $sumar = function (?int $caja, string $metodo, string $campo, int $importe) use (&$filas): void {
            $clave = (int) $caja.'|'.$metodo;
            $filas[$clave] ??= ['caja_id' => $caja, 'metodo' => $metodo, 'entradas' => 0, 'salidas' => 0];
            $filas[$clave][$campo] += $importe;
        };
        foreach (DB::table('pagos')->where('propiedad_id', $propiedad->id)->where('fecha', $dia)->groupBy('caja_id', 'metodo')->get(['caja_id', 'metodo', DB::raw('SUM(importe) as total')]) as $f) {
            $sumar($f->caja_id === null ? null : (int) $f->caja_id, (string) $f->metodo, 'entradas', (int) $f->total);
        }
        foreach (DB::table('movimientos')->where('propiedad_id', $propiedad->id)->where('fecha', $dia)->groupBy('caja_id', 'metodo', 'tipo')->get(['caja_id', 'metodo', 'tipo', DB::raw('SUM(importe) as total')]) as $f) {
            $sumar($f->caja_id === null ? null : (int) $f->caja_id, (string) $f->metodo, $f->tipo === TipoMovimiento::Gasto->value ? 'salidas' : 'entradas', (int) $f->total);
        }
        ksort($filas);

        return array_values(array_map(fn (array $f): array => ['caja' => $f['caja_id'] === null ? null : (string) $nombres->get($f['caja_id']), 'metodo' => $f['metodo'], 'entradas' => $f['entradas'], 'salidas' => $f['salidas']], $filas));
    }

    /**
     * Los arqueos del día, en el día de la propiedad.
     *
     * @return list<array{caja: string, esperado: int, contado: int, diferencia: int}>
     */
    private function turnos(Propiedad $propiedad, CarbonImmutable $fecha): array
    {
        $inicio = CarbonImmutable::parse($fecha->format('Y-m-d').' 00:00:00', $propiedad->zona_horaria);

        return TurnoCaja::query()->with('caja')->where('propiedad_id', $propiedad->id)
            ->whereBetween('cerrado_en', [$inicio->utc()->format('Y-m-d H:i:s'), $inicio->addDay()->subSecond()->utc()->format('Y-m-d H:i:s')])
            ->orderBy('cerrado_en')->get()
            ->map(fn (TurnoCaja $t): array => ['caja' => $t->caja->nombre, 'esperado' => (int) $t->esperado, 'contado' => (int) $t->contado, 'diferencia' => (int) $t->diferencia])
            ->values()->all();
    }

    /**
     * Cerrar otra vez el mismo día actualiza la foto.
     */
    public function cerrar(Propiedad $propiedad, CarbonImmutable $fecha, ?string $notas): CierreDia
    {
        $cierre = CierreDia::query()->updateOrCreate(
            ['propiedad_id' => $propiedad->id, 'fecha' => $fecha->format('Y-m-d')],
            ['cerrado_por' => auth()->id(), 'cerrado_en' => now('UTC'), 'notas' => $notas, 'resumen' => $this->resumen($propiedad, $fecha)],
        );
        RegistroAuditoria::anotar('cierre_dia.cerrar', $cierre, ['fecha' => $fecha->format('Y-m-d')], $propiedad->id);

        return $cierre;
    }
}
