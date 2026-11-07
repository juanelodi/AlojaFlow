<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Paises;

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

    public function sugerenciasImpuesto(): array
    {
        return CatalogoIsh::todas();
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
        ];
    }

    /**
     * Persona física con RFC: 4 % de ISR y la mitad del IVA, sobre el ingreso sin IVA (LISR 113-A, LIVA 18-J). Sin RFC son 20 % y 16 %; se cambian en el canal.
     */
    public function cargosDeCanales(): array
    {
        return [
            'comisiones' => ['airbnb' => 16.0],
            'cargos' => [
                ['clave' => 'iva_comision', 'clase' => \App\Enums\ClaseCargoCanal::ImpuestoSobreCargos, 'valor' => 16.0, 'base' => \App\Enums\BaseCargoCanal::Comisiones],
                ['clave' => 'retencion_isr', 'clase' => \App\Enums\ClaseCargoCanal::RetencionRenta, 'valor' => 4.0, 'base' => \App\Enums\BaseCargoCanal::SinImpuestos],
                ['clave' => 'retencion_iva', 'clase' => \App\Enums\ClaseCargoCanal::RetencionIva, 'valor' => 8.0, 'base' => \App\Enums\BaseCargoCanal::SinImpuestos],
            ],
        ];
    }
}
