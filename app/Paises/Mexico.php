<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

use App\Facturacion\Cfdi40;
use App\Facturacion\GeneradorFiscal;

/**
 * México: sin registro nacional de viajeros conocido; es una tarjeta de registro del propio hotel.
 * Hay que revisar con un abogado el aviso de privacidad y los requisitos de cada estado
 * (docs/paises/mexico.md).
 */
final class Mexico extends PaisBase
{
    protected function clave(): string
    {
        return 'mexico';
    }

    public function generadorFiscal(): GeneradorFiscal
    {
        return new Cfdi40();
    }

    public function tiposDocumento(): array
    {
        return [
            'ine' => __('legal.documentos.ine'),
            'pasaporte' => __('legal.documentos.pasaporte'),
            'licencia' => __('legal.documentos.licencia'),
            'fmm' => __('legal.documentos.fmm'),
            'otro' => __('legal.documentos.otro'),
        ];
    }

    public function camposLlegada(): array
    {
        return [
            'procedencia' => ['etiqueta' => __('legal.campos.procedencia'), 'requerido' => true, 'solo_titular' => true],
            'motivo_viaje' => ['etiqueta' => __('legal.campos.motivo_viaje'), 'requerido' => false, 'solo_titular' => true],
            'placas' => ['etiqueta' => __('legal.campos.placas'), 'requerido' => false, 'solo_titular' => true],
            'rfc' => ['etiqueta' => __('legal.campos.rfc'), 'requerido' => false, 'solo_titular' => true],
        ];
    }
}
