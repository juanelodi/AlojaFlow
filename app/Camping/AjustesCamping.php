<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Camping;

use App\Enums\ServicioMedido;
use App\Models\Propiedad;

/**
 * A cuánto cobra cada propiedad lo que marcan sus medidores. Como texto y con decimales: el kWh no cuesta centavos justos.
 */
final class AjustesCamping
{
    public function __construct(private readonly Propiedad $propiedad)
    {
    }

    public function tarifa(ServicioMedido $servicio): ?string
    {
        $tarifa = ((array) $this->propiedad->ajustes)['camping']['tarifas'][$servicio->value] ?? null;

        return is_numeric($tarifa) && (float) $tarifa > 0 ? (string) $tarifa : null;
    }

    /**
     * @param  array<string, mixed>  $tarifas  servicio => precio
     */
    public function guardarTarifas(array $tarifas): void
    {
        $ajustes = (array) $this->propiedad->ajustes;
        $ajustes['camping']['tarifas'] = array_map(fn (mixed $tarifa): ?string => is_numeric($tarifa) ? (string) $tarifa : null, $tarifas);
        $this->propiedad->ajustes = $ajustes;
        $this->propiedad->save();
    }
}
