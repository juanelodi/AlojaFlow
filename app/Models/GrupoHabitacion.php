<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que el grupo sabe de cada habitación, además de su reserva: su precio por noche y quién va (D-094).
 *
 * @property int $id
 * @property int $grupo_id
 * @property int $reserva_id
 * @property int|null $precio_noche
 * @property string|null $responsable
 * @property string|null $telefono
 * @property string|null $correo
 * @property string|null $acompanantes
 * @property-read Grupo $grupo
 * @property-read Reserva $reserva
 */
class GrupoHabitacion extends ModeloBase
{
    protected $table = 'grupo_habitaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['precio_noche' => 'integer'];
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
