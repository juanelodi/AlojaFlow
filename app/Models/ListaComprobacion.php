<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoTarea;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que hay que revisar en cada tarea de un tipo. Cada tarea guarda su copia: cambiar la lista no altera lo ya hecho.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property TipoTarea $tipo_tarea
 * @property string $nombre
 * @property array<int|string, string> $puntos
 * @property bool $activa
 * @property Propiedad $propiedad
 */
class ListaComprobacion extends ModeloBase
{
    protected $table = 'listas_comprobacion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tipo_tarea' => TipoTarea::class, 'puntos' => 'array', 'activa' => 'boolean'];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
