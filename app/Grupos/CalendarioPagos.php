<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Grupos;

use App\Models\CuotaGrupo;
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
        // Como texto: vence_en no tiene zona y hoy() sí. Si no, lo que vence hoy saldría atrasado.
        $hoy = $grupo->propiedad->hoy()->format('Y-m-d');
        $restante = max(0, $saldo['pagado']);
        $filas = [];
        $suma = 0;
        foreach ($grupo->cuotas()->orderBy('vence_en')->orderBy('id')->get() as $cuota) {
            /** @var CuotaGrupo $cuota */
            $aplicado = min($restante, $cuota->importe);
            $restante -= $aplicado;
            $suma += $cuota->importe;
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

        return ['cuotas' => $filas, 'suma' => $suma, 'total' => $saldo['total'], 'cuadra' => $filas === [] || $suma === $saldo['total'], 'pagado' => $saldo['pagado']];
    }
}
