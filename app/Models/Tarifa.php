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
 * Precio por noche de una propiedad independiente, con temporadas que lo sustituyen (D-036).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property int $precio_noche
 * @property bool $activa
 * @property-read Propiedad $propiedad
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TemporadaTarifa> $temporadas
 */
class Tarifa extends ModeloBase
{
    protected $table = 'tarifas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'precio_noche' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<TemporadaTarifa, $this> */
    public function temporadas(): HasMany
    {
        return $this->hasMany(TemporadaTarifa::class)->orderBy('desde');
    }

    /** @return HasMany<Unidad, $this> */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }
}
