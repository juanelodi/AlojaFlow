<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Models\Reserva;
use Illuminate\Http\Request;

/**
 * Una pasarela de pago. Se usa la página de pago de la propia pasarela: los datos de la tarjeta
 * nunca pasan por AlojaFlow (D-042).
 */
interface ProveedorPago
{
    public function nombre(): string;

    public function iniciar(Reserva $reserva, int $importe, string $concepto, string $urlExito, string $urlCancelar, string $urlAviso, string $referenciaInterna): InicioPago;

    /**
     * Consulta el cobro a la pasarela al volver el huésped a nuestra página.
     */
    public function consultarRetorno(Request $request, string $referencia): ?ResultadoPago;

    /**
     * Interpreta un aviso (webhook). Devuelve null si no es de un cobro que interese; lanza
     * ErrorPago si la firma no es válida.
     */
    public function procesarAviso(Request $request): ?ResultadoPago;
}
