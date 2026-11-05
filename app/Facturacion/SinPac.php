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
 * Lo que hay mientras no se contrate un PAC: dice con claridad que no se puede timbrar.
 */
final class SinPac implements ConectorPac
{
    public function timbrar(string $xml, EntidadFiscal $entidad): array
    {
        throw new ErrorFacturacion(__('dinero.facturas.sin_pac'));
    }

    public function cancelar(string $uuid, string $motivo, EntidadFiscal $entidad, ?string $folioSustitucion = null): array
    {
        throw new ErrorFacturacion(__('dinero.facturas.sin_pac'));
    }
}
