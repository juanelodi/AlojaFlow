<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impuesto de una propiedad independiente: incluido en el precio o que se suma (modelo-datos.md §4.10).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string|null $codigo
 * @property string $nombre
 * @property BaseCargo $base
 * @property string|null $tasa
 * @property int|null $importe
 * @property bool $incluido_en_precio
 * @property CarbonImmutable|null $vigente_desde
 * @property CarbonImmutable|null $vigente_hasta
 * @property int|null $tope_noches
 * @property int|null $edad_minima
 * @property int $orden
 * @property bool $activo
 */
class Impuesto extends ModeloBase
{
    protected $table = 'impuestos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base' => BaseCargo::class,
            'tasa' => 'decimal:4',
            'importe' => 'integer',
            'incluido_en_precio' => 'boolean',
            'vigente_desde' => 'immutable_date',
            'vigente_hasta' => 'immutable_date',
            'tope_noches' => 'integer',
            'edad_minima' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /**
     * Los que aplican a una fecha de llegada.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAplicables(Builder $query, int $propiedadId, string $fecha): Builder
    {
        return $query->where('propiedad_id', $propiedadId)->where('activo', true)
            ->where(fn (Builder $q): Builder => $q->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $fecha))
            ->where(fn (Builder $q): Builder => $q->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $fecha));
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
