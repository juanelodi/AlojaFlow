<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargo;
use Carbon\CarbonImmutable;
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
 * @property list<int>|null $dias_semana del lunes (1) al domingo (7)
 * @property int|null $cada_noches
 * @property CarbonImmutable|null $desde
 * @property CarbonImmutable|null $hasta
 * @property int|null $noches_minimas
 * @property list<int>|null $solo_tarifas
 * @property list<int>|null $solo_unidades
 * @property bool $por_cama_extra
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
            'dias_semana' => 'array',
            'cada_noches' => 'integer',
            'desde' => 'immutable_date',
            'hasta' => 'immutable_date',
            'noches_minimas' => 'integer',
            'solo_tarifas' => 'array',
            'solo_unidades' => 'array',
            'por_cama_extra' => 'boolean',
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
