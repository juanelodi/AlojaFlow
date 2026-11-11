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
use Illuminate\Support\Carbon;

/**
 * Fechas ocupadas en un portal. Sin datos personales: el texto del evento no se guarda salvo el genérico.
 *
 * @property int $id
 * @property int $fuente_id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property string $uid
 * @property CarbonImmutable $llegada
 * @property CarbonImmutable $salida
 * @property string|null $resumen
 * @property Carbon $primera_vez_en
 * @property Carbon $visto_en
 * @property Carbon|null $eliminado_en
 * @property FuenteIcal $fuente
 * @property Unidad $unidad
 */
class BloqueoExterno extends ModeloBase
{
    protected $table = 'bloqueos_externos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'llegada' => 'immutable_date',
            'salida' => 'immutable_date',
            'primera_vez_en' => 'datetime',
            'visto_en' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<FuenteIcal, $this> */
    public function fuente(): BelongsTo
    {
        return $this->belongsTo(FuenteIcal::class, 'fuente_id');
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
