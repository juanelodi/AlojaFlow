<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que no se vende entre dos fechas (D-084): cerrar llegadas o salidas, días de entrada, estancia máxima, anticipación máxima y noches
 * de preparación. Sin unidad, vale para toda la propiedad.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $unidad_id
 * @property string|null $nombre
 * @property CarbonImmutable $desde
 * @property CarbonImmutable $hasta
 * @property bool $cerrado_llegada
 * @property bool $cerrado_salida
 * @property int|null $dias_llegada
 * @property int|null $estancia_maxima
 * @property int|null $anticipacion_maxima
 * @property int|null $noches_preparacion
 * @property bool $activa
 * @property-read Propiedad $propiedad
 * @property-read Unidad|null $unidad
 */
class Restriccion extends ModeloBase
{
    protected $table = 'restricciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'cerrado_llegada' => 'boolean',
            'cerrado_salida' => 'boolean',
            'dias_llegada' => 'integer',
            'estancia_maxima' => 'integer',
            'anticipacion_maxima' => 'integer',
            'noches_preparacion' => 'integer',
            'activa' => 'boolean',
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
}
