<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un concepto de una factura.
 *
 * @property int $id
 * @property int $factura_id
 * @property int $orden
 * @property string $concepto
 * @property string|null $clave_producto
 * @property string|null $clave_unidad
 * @property string $cantidad
 * @property string $valor_unitario
 * @property int $importe
 * @property \Illuminate\Database\Eloquent\Collection<int, FacturaImpuesto> $impuestos
 */
class FacturaLinea extends ModeloBase
{
    protected $table = 'facturas_lineas';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:6',
            'valor_unitario' => 'decimal:6',
            'importe' => 'integer',
        ];
    }

    /** @return BelongsTo<Factura, $this> */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    /** @return HasMany<FacturaImpuesto, $this> */
    public function impuestos(): HasMany
    {
        return $this->hasMany(FacturaImpuesto::class);
    }
}
