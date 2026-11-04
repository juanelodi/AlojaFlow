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
 * Color en el calendario según un campo de la ficha del huésped (empresa, agencia, VIP…).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $campo
 * @property string $valor
 * @property string $color
 */
class ColorTipoCliente extends ModeloBase
{
    protected $table = 'colores_tipo_cliente';

    protected $guarded = ['id'];

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
