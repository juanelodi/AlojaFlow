<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * El plano de un piso o edificio, con las unidades colocadas encima.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property int|null $archivo_id
 * @property int $orden
 */
class Plano extends ModeloBase
{
    protected $table = 'planos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    /** @return HasMany<PlanoUnidad, $this> */
    public function posiciones(): HasMany
    {
        return $this->hasMany(PlanoUnidad::class);
    }
}
