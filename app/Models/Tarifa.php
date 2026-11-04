<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoTarifa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Precio por noche de una propiedad independiente, con temporadas que lo sustituyen (D-036).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property ModoTarifa $modo
 * @property int $precio_noche
 * @property int $personas_incluidas
 * @property int $precio_persona_extra
 * @property int|null $precio_fin_semana
 * @property int $estancia_minima
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
            'modo' => ModoTarifa::class,
            'precio_noche' => 'integer',
            'personas_incluidas' => 'integer',
            'precio_persona_extra' => 'integer',
            'precio_fin_semana' => 'integer',
            'estancia_minima' => 'integer',
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
