<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Dinero;

use App\Enums\EstadoReserva;
use App\Enums\TipoReserva;
use App\Models\Canal;
use App\Models\MarcaNoShow;
use App\Models\Politica;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Reservas\GestorReservas;
use App\Reservas\ReservaRechazada;
use Illuminate\Support\Facades\DB;

/**
 * Cuánto se cobra si cancelan o no llegan. AlojaFlow calcula y recuerda; cobrar o devolver lo decide
 * quien está en recepción.
 */
final class GestorPoliticas
{
    public function aplicable(Reserva $reserva): ?Politica
    {
        $delCanal = filled($reserva->origen_texto)
            ? Canal::query()->where('propiedad_id', $reserva->propiedad_id)->where('nombre', $reserva->origen_texto)->whereNotNull('politica_id')->value('politica_id')
            : null;
        if ($delCanal !== null) {
            return Politica::query()->find($delCanal);
        }

        return Politica::query()->where('propiedad_id', $reserva->propiedad_id)->where('predeterminada', true)->first();
    }

    public function penalizacionCancelacion(Reserva $reserva, Politica $politica): int
    {
        $dias = (int) $reserva->propiedad->hoy()->diffInDays($reserva->llegada, false);
        if ($dias >= $politica->dias_cancelacion_gratuita) {
            return 0;
        }

        return (int) round($reserva->total * (float) $politica->penalizacion_porcentaje / 100);
    }

    public function penalizacionNoShow(Reserva $reserva, Politica $politica): int
    {
        return (int) round($reserva->total * (float) $politica->no_show_porcentaje / 100);
    }

    /**
     * Lo que pide la política y no se ha cobrado todavía.
     */
    public function depositoPendiente(Reserva $reserva, Politica $politica): int
    {
        return max(0, (int) round($reserva->total * (float) $politica->deposito_porcentaje / 100) - $reserva->pagado);
    }

    /**
     * Texto para el cuadro de cancelar: qué dice la política y cuánto hay que devolver.
     */
    public function resumenCancelacion(Reserva $reserva): ?string
    {
        $politica = $this->aplicable($reserva);
        if ($politica === null) {
            return null;
        }
        $penalizacion = $this->penalizacionCancelacion($reserva, $politica);
        $moneda = $reserva->moneda;

        return __('dinero.politicas.resumen_cancelacion', [
            'politica' => $politica->nombre,
            'penalizacion' => \App\Support\Formato::dinero($penalizacion, $moneda),
            'pagado' => \App\Support\Formato::dinero($reserva->pagado, $moneda),
            'devolver' => \App\Support\Formato::dinero(max(0, $reserva->pagado - $penalizacion), $moneda),
            'cobrar' => \App\Support\Formato::dinero(max(0, $penalizacion - $reserva->pagado), $moneda),
        ]);
    }

    public function puedeMarcarNoShow(Reserva $reserva): bool
    {
        return $reserva->tipo === TipoReserva::Reserva
            && $reserva->estado !== EstadoReserva::Cancelada
            && $reserva->entrada_registrada_en === null
            && $reserva->llegada->format('Y-m-d') <= $reserva->propiedad->hoy()->format('Y-m-d')
            && ! MarcaNoShow::query()->where('reserva_id', $reserva->id)->exists();
    }

    /**
     * En una independiente además libera las noches. En una subordinada solo se anota: la reserva
     * se cancela en la principal.
     */
    public function marcarNoShow(Reserva $reserva, ?string $nota): MarcaNoShow
    {
        if (! $this->puedeMarcarNoShow($reserva)) {
            throw new ReservaRechazada(__('dinero.politicas.error_no_show'));
        }
        $politica = $this->aplicable($reserva);

        return DB::transaction(function () use ($reserva, $nota, $politica): MarcaNoShow {
            $marca = MarcaNoShow::query()->create([
                'reserva_id' => $reserva->id,
                'marcada_por' => auth()->id(),
                'penalizacion' => $politica === null ? 0 : $this->penalizacionNoShow($reserva, $politica),
                'moneda' => $reserva->moneda,
                'nota' => $nota,
            ]);
            if (! $reserva->sincronizada) {
                app(GestorReservas::class)->cancelar($reserva);
            }
            RegistroAuditoria::anotar('reserva.no_show', $reserva, ['penalizacion' => $marca->penalizacion], $reserva->propiedad_id);

            return $marca;
        });
    }
}
