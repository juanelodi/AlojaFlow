<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

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
    ) {
    }
}
