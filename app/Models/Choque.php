<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoChoque;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dos ocupaciones de la misma unidad en las mismas fechas.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property CarbonImmutable $desde
 * @property CarbonImmutable $hasta
 * @property TipoChoque $tipo
 * @property int|null $bloqueo_externo_id
 * @property int $reserva_id
 * @property int|null $reserva2_id
 * @property string|null $clave_unica
 * @property Carbon $detectado_en
 * @property Carbon|null $resuelto_en
 * @property int|null $resuelto_por
 * @property string|null $nota
 * @property Unidad $unidad
 * @property Reserva $reserva
 * @property Reserva|null $reserva2
 * @property BloqueoExterno|null $bloqueoExterno
 */
class Choque extends ModeloBase
{
    protected $table = 'choques';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'tipo' => TipoChoque::class,
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'detectado_en' => 'datetime',
            'resuelto_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva2(): BelongsTo
    {
        return $this->belongsTo(Reserva::class, 'reserva2_id');
    }

    /** @return BelongsTo<BloqueoExterno, $this> */
    public function bloqueoExterno(): BelongsTo
    {
        return $this->belongsTo(BloqueoExterno::class, 'bloqueo_externo_id');
    }
}
