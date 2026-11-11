<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Reservas;

use App\Enums\EstadoReserva;
use App\Enums\ModoPropiedad;
use App\Enums\TipoReserva;
use App\Models\MarcaEstancia;
use App\Models\RegistroAuditoria;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Registrar que el huésped llegó o salió (D-153). En una propiedad independiente lo hace GestorReservas en la
 * reserva; en una subordinada, la reserva es de la principal y se anota una marca propia.
 */
final class GestorEstancias
{
    public function __construct(private readonly GestorReservas $reservas)
    {
    }

    /**
     * @throws ReservaRechazada
     */
    public function registrarEntrada(Reserva $reserva, ?User $quien): void
    {
        if ($reserva->propiedad->modo === ModoPropiedad::Nativa) {
            $this->reservas->registrarEntrada($reserva);

            return;
        }
        DB::transaction(function () use ($reserva, $quien): void {
            $marca = MarcaEstancia::query()->lockForUpdate()->firstOrNew(['reserva_id' => $reserva->id]);
            // La principal también puede haberla registrado: entonces ya llegó.
            if ($reserva->tipo !== TipoReserva::Reserva || $reserva->estado === EstadoReserva::Cancelada || $reserva->entrada_registrada_en !== null || $marca->entrada_en !== null) {
                throw new ReservaRechazada(__('ui.reservas.error_entrada'));
            }
            if ($reserva->llegada->format('Y-m-d') > $reserva->propiedad->hoy()->format('Y-m-d')) {
                throw new ReservaRechazada(__('ui.reservas.error_entrada_futura'));
            }
            $this->guardar($marca->fill(['entrada_en' => now('UTC'), 'entrada_por' => $quien?->id]), __('ui.reservas.error_entrada'));
            RegistroAuditoria::anotar('reserva.entrada', $reserva, ['accion' => 'entrada', 'subordinada' => true]);
        });
    }

    /**
     * @throws ReservaRechazada
     */
    public function registrarSalida(Reserva $reserva, ?User $quien): void
    {
        if ($reserva->propiedad->modo === ModoPropiedad::Nativa) {
            $this->reservas->registrarSalida($reserva);

            return;
        }
        DB::transaction(function () use ($reserva, $quien): void {
            // Si la llegada la registró la principal, aquí solo se anota la salida.
            $marca = MarcaEstancia::query()->lockForUpdate()->firstOrNew(['reserva_id' => $reserva->id]);
            if (($reserva->entrada_registrada_en ?? $marca->entrada_en) === null || $reserva->salida_registrada_en !== null || $marca->salida_en !== null) {
                throw new ReservaRechazada(__('ui.reservas.error_salida'));
            }
            $this->guardar($marca->fill(['salida_en' => now('UTC'), 'salida_por' => $quien?->id]), __('ui.reservas.error_salida'));
            RegistroAuditoria::anotar('reserva.salida', $reserva, ['accion' => 'salida', 'subordinada' => true]);
        });
    }

    /**
     * Si alguien la marcó al mismo tiempo, la marca nueva choca con la suya: se dice como si ya estuviera.
     */
    private function guardar(MarcaEstancia $marca, string $siYaEstaba): void
    {
        try {
            $marca->save();
        } catch (UniqueConstraintViolationException) {
            throw new ReservaRechazada($siYaEstaba);
        }
    }
}
