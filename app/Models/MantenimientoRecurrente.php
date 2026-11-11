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
 * Un trabajo que se repite cada cierto tiempo: cambiar filtros, fumigar. Genera una tarea cuando se acerca.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $unidad_id
 * @property string $titulo
 * @property string|null $descripcion
 * @property int $cada_dias
 * @property CarbonImmutable $proxima_fecha
 * @property bool $activo
 * @property Propiedad $propiedad
 * @property Unidad|null $unidad
 */
class MantenimientoRecurrente extends ModeloBase
{
    protected $table = 'mantenimientos_recurrentes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['proxima_fecha' => 'immutable_date', 'activo' => 'boolean', 'cada_dias' => 'integer'];
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
