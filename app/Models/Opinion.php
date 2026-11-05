<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que un huésped contestó en la encuesta tras su estancia (D-087).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property int|null $calificacion
 * @property string|null $comentario
 * @property Carbon|null $respondida_en
 * @property-read Reserva $reserva
 * @property-read Propiedad $propiedad
 */
class Opinion extends ModeloBase
{
    protected $table = 'opiniones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['calificacion' => 'integer', 'respondida_en' => 'datetime'];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
