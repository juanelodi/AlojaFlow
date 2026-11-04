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
 * Historial de los cambios de estado de una unidad.
 *
 * @property int $id
 * @property int $unidad_id
 * @property string $campo
 * @property string|null $antes
 * @property string|null $despues
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon|null $creado_en
 */
class CambioEstadoUnidad extends ModeloBase
{
    protected $table = 'cambios_estado_unidad';

    protected $guarded = ['id'];

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
