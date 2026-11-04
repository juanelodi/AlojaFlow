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
 * Varias reservas de un mismo viaje o evento, con un coordinador.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string $nombre
 * @property int|null $coordinador_id
 * @property string|null $notas
 * @property-read Propiedad $propiedad
 * @property-read Huesped|null $coordinador
 */
class Grupo extends ModeloBase
{
    protected $table = 'grupos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Huesped, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Huesped::class, 'coordinador_id');
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
