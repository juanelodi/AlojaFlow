<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Motor;

use App\Models\Propiedad;
use App\Pagos\ProveedorMercadoPago;
use App\Pagos\ProveedorPago;
use App\Pagos\ProveedorStripe;

/**
 * Las pasarelas de pago que tiene configuradas una propiedad.
 */
final class Pasarelas
{
    public const STRIPE = 'stripe';

    public const MERCADOPAGO = 'mercadopago';

    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    /**
     * @return array<string, ProveedorPago>
     */
    public function disponibles(): array
    {
        $ajustes = new AjustesMotor($this->propiedad);
        $disponibles = [];
        if ($ajustes->tieneLlave('stripe_secreta')) {
            $disponibles[self::STRIPE] = new ProveedorStripe((string) $ajustes->llave('stripe_secreta'), $ajustes->llave('stripe_webhook'));
        }
        if ($ajustes->tieneLlave('mercadopago_token')) {
            $disponibles[self::MERCADOPAGO] = new ProveedorMercadoPago((string) $ajustes->llave('mercadopago_token'), $ajustes->llave('mercadopago_webhook'));
        }

        return $disponibles;
    }

    public function proveedor(string $nombre): ?ProveedorPago
    {
        return $this->disponibles()[$nombre] ?? null;
    }
}
