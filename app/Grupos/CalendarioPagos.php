<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Models\CuotaGrupo;
use App\Models\CuotaReserva;
use App\Models\Grupo;
use App\Reservas\GestorGrupos;

/**
 * Qué cuota está pagada no se guarda: lo pagado se aplica a las primeras en vencer. Así, confirmar o quitar un pago nunca deja el calendario desfasado.
 */
final class CalendarioPagos
{
    /**
     * @return array{cuotas: list<array{cuota: CuotaGrupo, pagado: int, estado: string}>, suma: int, total: int, cuadra: bool, pagado: int}
     */
    public static function de(Grupo $grupo): array
    {
        $saldo = app(GestorGrupos::class)->saldo($grupo);
        $cuotas = $grupo->cuotas()->orderBy('vence_en')->orderBy('id')->get();
        $filas = self::repartir($cuotas, $saldo['pagado'], $grupo->propiedad->hoy()->format('Y-m-d'));
        $suma = (int) $cuotas->sum('importe');

        return ['cuotas' => $filas, 'suma' => $suma, 'total' => $saldo['total'], 'cuadra' => $filas === [] || $suma === $saldo['total'], 'pagado' => $saldo['pagado']];
    }

    /**
     * Aplica lo pagado a las primeras en vencer. Con la fecha como texto: vence_en no tiene zona y hoy() sí, y lo que vence hoy saldría atrasado.
     *
     * @template T of CuotaGrupo|CuotaReserva
     *
     * @param  iterable<T>  $cuotas  en orden de vencimiento
     * @return list<array{cuota: T, pagado: int, estado: string}>
     */
    public static function repartir(iterable $cuotas, int $pagado, string $hoy): array
    {
        $restante = max(0, $pagado);
        $filas = [];
        foreach ($cuotas as $cuota) {
            $aplicado = min($restante, $cuota->importe);
            $restante -= $aplicado;
            $filas[] = [
                'cuota' => $cuota,
                'pagado' => $aplicado,
                'estado' => match (true) {
                    $aplicado >= $cuota->importe => 'pagada',
                    $cuota->vence_en->format('Y-m-d') < $hoy => 'atrasada',
                    $aplicado > 0 => 'parcial',
                    default => 'pendiente',
                },
            ];
        }

        return $filas;
    }
}
