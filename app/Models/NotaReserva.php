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
 * Nota interna del personal sobre una reserva; el huésped no la ve.
 *
 * @property int $id
 * @property int $reserva_id
 * @property int|null $user_id
 * @property string $texto
 */
class NotaReserva extends ModeloBase
{
    protected $table = 'notas_reserva';

    protected $guarded = ['id'];

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
