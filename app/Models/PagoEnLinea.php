<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Intento de cobro con una pasarela. La referencia única evita registrar dos veces el mismo pago.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property int|null $enlace_pago_id
 * @property int|null $pago_id
 * @property string $pasarela
 * @property string|null $referencia
 * @property string $estado
 * @property int $importe
 * @property string $moneda
 * @property array<string, mixed>|null $datos
 * @property-read Reserva $reserva
 */
class PagoEnLinea extends ModeloBase
{
    protected $table = 'pagos_en_linea';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'importe' => 'integer',
            'datos' => 'array',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
