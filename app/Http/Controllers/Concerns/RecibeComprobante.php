<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Comprobantes\ComprobanteRechazado;
use App\Comprobantes\GestorComprobantes;
use App\Models\Reserva;
use App\Support\Dinero;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * El comprobante que manda el huésped desde una página sin sesión: con errores vuelve a la página y le dice qué pasó, porque no hay con qué devolverle el formulario.
 */
trait RecibeComprobante
{
    /**
     * @return string el aviso que se le muestra
     */
    private function recibirComprobante(Request $request, Reserva $reserva, GestorComprobantes $comprobantes): string
    {
        $validador = Validator::make($request->all(), [
            'importe' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'fecha' => ['required', 'date', 'before_or_equal:'.$reserva->propiedad->hoy()->addDay()->format('Y-m-d')],
            'referencia' => ['nullable', 'string', 'max:100'],
            'comprobante' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        if ($validador->fails()) {
            return 'comprobante_datos';
        }
        $datos = $validador->validated();
        try {
            $comprobantes->recibirDeReserva(
                $reserva,
                (string) file_get_contents((string) $request->file('comprobante')?->getRealPath()),
                Dinero::aUnidadMenor((string) $datos['importe'], $reserva->moneda),
                CarbonImmutable::parse((string) $datos['fecha'])->startOfDay(),
                $datos['referencia'] ?? null,
            );
        } catch (ComprobanteRechazado) {
            return 'comprobante_imagen';
        }

        return 'comprobante_recibido';
    }
}
