<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una noche ocupada por una reserva (D-019).
 *
 * @property int $id
 * @property int $reserva_id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property CarbonImmutable $fecha
 * @property int $importe
 * @property int|null $exclusiva
 */
class ReservaNoche extends ModeloBase
{
    protected $table = 'reservas_noches';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'immutable_date',
            'importe' => 'integer',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
