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
 * Línea de cargo adicional de una reserva. Guarda nombre e importe para que un cambio en el catálogo no altere lo ya vendido.
 *
 * @property int $id
 * @property int $reserva_id
 * @property int|null $concepto_cargo_id
 * @property string $nombre
 * @property string $cantidad
 * @property int $importe
 */
class CargoReserva extends ModeloBase
{
    protected $table = 'reservas_cargos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'importe' => 'integer',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<ConceptoCargo, $this> */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(ConceptoCargo::class, 'concepto_cargo_id');
    }
}
