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
 * Lo que un turno le deja dicho al siguiente (D-080).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $user_id
 * @property string $texto
 * @property bool $importante
 * @property Carbon|null $resuelta_en
 * @property int|null $resuelta_por
 * @property string|null $clave_generacion
 * @property Carbon|null $creado_en
 * @property-read Propiedad $propiedad
 * @property-read User|null $autor
 */
class NotaBitacora extends ModeloBase
{
    protected $table = 'notas_bitacora';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importante' => 'boolean', 'resuelta_en' => 'datetime'];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
