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
 * El huésped no se presentó. Es un dato propio: no se escribe en la principal.
 *
 * @property int $id
 * @property int $reserva_id
 * @property int|null $marcada_por
 * @property int $penalizacion
 * @property string $moneda
 * @property string|null $nota
 */
class MarcaNoShow extends ModeloBase
{
    protected $table = 'marcas_no_show';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'penalizacion' => 'integer',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
