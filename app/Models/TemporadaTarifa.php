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
 * Precio de una tarifa entre dos fechas; «hasta» es la última noche, inclusive.
 *
 * @property int $id
 * @property int $tarifa_id
 * @property string $nombre
 * @property CarbonImmutable $desde
 * @property CarbonImmutable $hasta
 * @property int $precio_noche
 * @property int|null $precio_persona_extra
 * @property int|null $precio_fin_semana
 * @property int|null $estancia_minima
 */
class TemporadaTarifa extends ModeloBase
{
    protected $table = 'tarifas_temporadas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'precio_noche' => 'integer',
            'precio_persona_extra' => 'integer',
            'precio_fin_semana' => 'integer',
            'estancia_minima' => 'integer',
        ];
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }
}
