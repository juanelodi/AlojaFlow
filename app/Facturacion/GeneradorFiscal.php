<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Models\Factura;

/**
 * El documento fiscal de un país. Sin generador, la propiedad emite recibos sin valor fiscal.
 */
interface GeneradorFiscal
{
    /**
     * Listas para el formulario: regimen, uso, forma_pago y metodo_pago (código => nombre).
     *
     * @return array<string, array<int|string, string>>
     */
    public function catalogos(): array;

    /**
     * El motivo por el que no se puede facturar con estos datos, o null si están bien.
     *
     * @param  array<string, mixed>  $receptor
     */
    public function errorEnReceptor(array $receptor): ?string;

    /**
     * El documento fiscal ya generado (por ejemplo, el XML del CFDI).
     */
    public function generar(Factura $factura): string;

    public function nombreArchivo(Factura $factura): string;

    /**
     * Clave de producto y de unidad de un concepto de hospedaje.
     *
     * @return array{producto: string|null, unidad: string|null}
     */
    public function clavesHospedaje(): array;
}
