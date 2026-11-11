<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Pagos;

use App\Enums\MetodoPago;

/**
 * Lo que la pasarela confirma sobre un cobro. Siempre sale de su propia API o de un aviso con firma
 * válida, nunca de lo que diga el navegador del huésped.
 */
final readonly class ResultadoPago
{
    public function __construct(
        public string $referencia,
        public bool $pagado,
        public int $importe,
        public string $moneda,
        public ?string $idPago = null,
        // Eligió OXXO o transferencia y falta que pague: no es un pago, pero sus noches no deben soltarse.
        public bool $enEspera = false,
        public ?string $instrucciones = null,
        public ?int $venceEn = null,
        // El vale venció sin pagarse.
        public bool $fallido = false,
        public ?MetodoPago $metodo = null,
    ) {
    }
}
