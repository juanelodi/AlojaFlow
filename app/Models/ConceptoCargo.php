<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cargo adicional del catálogo: cama extra, limpieza, desayuno… (modelo-datos.md §5).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property BaseCargo $base
 * @property int $importe
 * @property string|null $porcentaje
 * @property int|null $cupo_diario
 * @property bool $para_administrador
 * @property bool $obligatorio
 * @property bool $visible_motor
 * @property bool $activo
 * @property int $orden
 */
class ConceptoCargo extends ModeloBase
{
    protected $table = 'conceptos_cargo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base' => BaseCargo::class,
            'importe' => 'integer',
            'porcentaje' => 'decimal:4',
            'cupo_diario' => 'integer',
            'para_administrador' => 'boolean',
            'obligatorio' => 'boolean',
            'visible_motor' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
