<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoCupon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Código de descuento de una propiedad.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string|null $descripcion
 * @property TipoCupon $tipo
 * @property string|null $porcentaje
 * @property int|null $importe
 * @property CarbonImmutable|null $valido_desde
 * @property CarbonImmutable|null $valido_hasta
 * @property int|null $usos_maximos
 * @property int $estancia_minima
 * @property bool $activo
 * @property-read Propiedad $propiedad
 */
class Cupon extends ModeloBase
{
    protected $table = 'cupones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoCupon::class,
            'porcentaje' => 'decimal:4',
            'importe' => 'integer',
            'valido_desde' => 'immutable_date',
            'valido_hasta' => 'immutable_date',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
