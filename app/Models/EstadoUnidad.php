<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoLimpieza;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cómo está una unidad: va aparte de `unidades`, que en una subordinada es de la proyección (§2).
 *
 * @property int $id
 * @property int $unidad_id
 * @property EstadoLimpieza $limpieza
 * @property bool $fuera_de_servicio
 * @property string|null $nota
 * @property int|null $actualizado_por
 * @property \Illuminate\Support\Carbon|null $actualizado_en
 */
class EstadoUnidad extends ModeloBase
{
    protected $table = 'estados_unidad';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'limpieza' => EstadoLimpieza::class,
            'fuera_de_servicio' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }
}
