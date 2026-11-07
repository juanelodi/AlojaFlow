<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargoCanal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * De dónde vienen las reservas (directo, Booking…), con su comisión.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property string|null $comision_porcentaje
 * @property BaseCargoCanal $comision_base
 * @property bool $cobra_impuestos
 * @property bool $activo
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CanalCargo> $cargos
 */
class Canal extends ModeloBase
{
    protected $table = 'canales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'comision_porcentaje' => 'decimal:4',
            'comision_base' => BaseCargoCanal::class,
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<CanalCargo, $this> */
    public function cargos(): HasMany
    {
        return $this->hasMany(CanalCargo::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
