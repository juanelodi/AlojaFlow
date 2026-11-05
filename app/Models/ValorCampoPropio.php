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
 * Lo que un huésped o una reserva tiene en un campo propio (D-122). Vacío no se guarda.
 *
 * @property int $id
 * @property int $campo_propio_id
 * @property int|null $huesped_id
 * @property int|null $reserva_id
 * @property string $valor
 * @property-read CampoPropio $campo
 */
class ValorCampoPropio extends ModeloBase
{
    protected $table = 'valores_campo_propio';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<CampoPropio, $this> */
    public function campo(): BelongsTo
    {
        return $this->belongsTo(CampoPropio::class, 'campo_propio_id');
    }
}
