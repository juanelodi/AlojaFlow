<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Facturacion;

use App\Models\EntidadFiscal;

/**
 * Un Proveedor Autorizado de Certificación: sella y timbra el CFDI ante el SAT. AlojaFlow no trae
 * ninguno todavía (D-049); para conectar uno basta con implementar esta interfaz y registrarla en
 * `config('alojaflow.pac')`.
 */
interface ConectorPac
{
    /**
     * @return array{xml: string, uuid: string} el CFDI timbrado y su folio fiscal
     *
     * @throws ErrorFacturacion si el PAC lo rechaza
     */
    public function timbrar(string $xml, EntidadFiscal $entidad): array;

    /**
     * @throws ErrorFacturacion si el PAC lo rechaza
     */
    public function cancelar(string $uuid, string $motivo, EntidadFiscal $entidad): void;
}
