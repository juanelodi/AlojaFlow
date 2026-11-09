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
 * Llegó o salió el huésped de una reserva de una subordinada (D-153). Dato propio: no se escribe en la principal.
 *
 * @property int $id
 * @property int $reserva_id
 * @property Carbon|null $entrada_en
 * @property int|null $entrada_por
 * @property Carbon|null $salida_en
 * @property int|null $salida_por
 */
class MarcaEstancia extends ModeloBase
{
    protected $table = 'marcas_estancia';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entrada_en' => 'datetime',
            'salida_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
