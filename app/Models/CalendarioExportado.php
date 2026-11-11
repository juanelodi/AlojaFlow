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
 * El calendario de una unidad que se pega en los portales.
 *
 * @property int $id
 * @property int $unidad_id
 * @property int $enlace_publico_id
 * @property bool $incluir_bloqueos
 * @property bool $activo
 * @property Unidad $unidad
 * @property EnlacePublico $enlace
 */
class CalendarioExportado extends ModeloBase
{
    protected $table = 'calendarios_exportados';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'incluir_bloqueos' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<EnlacePublico, $this> */
    public function enlace(): BelongsTo
    {
        return $this->belongsTo(EnlacePublico::class, 'enlace_publico_id');
    }
}
