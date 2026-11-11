<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Propiedades\RegistrosTuristicos;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un número de registro o licencia, de toda la propiedad o de una unidad (D-091).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $unidad_id
 * @property string $tipo
 * @property string $numero
 * @property CarbonImmutable|null $vigente_hasta
 * @property bool $mostrar
 * @property-read Propiedad $propiedad
 * @property-read Unidad|null $unidad
 */
class RegistroTuristico extends ModeloBase
{
    protected $table = 'registros_turisticos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['vigente_hasta' => 'immutable_date', 'mostrar' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Quien renueva no tiene que esperar a la noche para que se quite el aviso.
        $revisar = fn (RegistroTuristico $registro) => RegistrosTuristicos::revisar($registro->propiedad_id);
        static::saved($revisar);
        static::deleted($revisar);
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
}
