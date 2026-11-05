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
 * Un Proveedor Autorizado de Certificación: sella y timbra el CFDI ante el SAT. AlojaFlow trae SW Sapien (D-098); para conectar otro basta con
 * implementar esta interfaz y agregarlo a `FabricaPac`.
 */
interface ConectorPac
{
    /**
     * @return array{xml: string, uuid: string, qr?: string|null} el CFDI timbrado, su folio fiscal y, si lo da, el código QR en base64
     *
     * @throws ErrorFacturacion si el PAC lo rechaza
     */
    public function timbrar(string $xml, EntidadFiscal $entidad): array;

    /**
     * @param  string  $motivo  la clave del SAT, del 01 al 04
     * @param  string|null  $folioSustitucion  el folio fiscal de la que la sustituye, con el motivo 01
     * @return array{acuse?: string|null, codigo?: string|null}
     *
     * @throws ErrorFacturacion si el PAC lo rechaza
     */
    public function cancelar(string $uuid, string $motivo, EntidadFiscal $entidad, ?string $folioSustitucion = null): array;
}
