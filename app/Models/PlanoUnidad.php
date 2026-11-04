<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dónde está una unidad en un plano, en porcentaje.
 *
 * @property int $id
 * @property int $plano_id
 * @property int $unidad_id
 * @property string $x
 * @property string $y
 * @property Unidad $unidad
 */
class PlanoUnidad extends ModeloBase
{
    protected $table = 'planos_unidades';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'x' => 'decimal:2',
            'y' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Plano, $this> */
    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }
}
