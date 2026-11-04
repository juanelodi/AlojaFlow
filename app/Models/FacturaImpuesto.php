<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

/**
 * Un impuesto de un concepto.
 *
 * @property int $id
 * @property int $factura_linea_id
 * @property string $nombre
 * @property string|null $codigo
 * @property string $tipo
 * @property int $base
 * @property string $tasa
 * @property int $importe
 */
class FacturaImpuesto extends ModeloBase
{
    protected $table = 'facturas_impuestos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'base' => 'integer',
            'tasa' => 'decimal:4',
            'importe' => 'integer',
        ];
    }
}
