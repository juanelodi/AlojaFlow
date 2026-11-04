<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFuenteIcal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Un calendario de un portal que se lee para bloquear esas fechas.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property int|null $canal_id
 * @property string $nombre
 * @property string $url
 * @property int $frecuencia_minutos
 * @property EstadoFuenteIcal $estado
 * @property Carbon|null $ultimo_intento_en
 * @property Carbon|null $ultimo_exito_en
 * @property string|null $ultimo_error
 * @property bool $activa
 * @property Propiedad $propiedad
 * @property Unidad $unidad
 */
class FuenteIcal extends ModeloBase
{
    protected $table = 'fuentes_ical';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'url' => 'encrypted',
            'frecuencia_minutos' => 'integer',
            'estado' => EstadoFuenteIcal::class,
            'ultimo_intento_en' => 'datetime',
            'ultimo_exito_en' => 'datetime',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Canal, $this> */
    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    /** @return HasMany<BloqueoExterno, $this> */
    public function bloqueos(): HasMany
    {
        return $this->hasMany(BloqueoExterno::class, 'fuente_id');
    }
}
