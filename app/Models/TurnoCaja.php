<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un turno de caja, con su fondo inicial y su arqueo (D-080).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $caja_id
 * @property int|null $caja_abierta
 * @property int|null $abierto_por
 * @property Carbon $abierto_en
 * @property int $fondo_inicial
 * @property string $moneda
 * @property int|null $cerrado_por
 * @property Carbon|null $cerrado_en
 * @property int|null $esperado
 * @property int|null $contado
 * @property int|null $diferencia
 * @property array<string, mixed>|null $resumen
 * @property string|null $notas
 * @property-read Propiedad $propiedad
 * @property-read Caja $caja
 * @property-read User|null $abridor
 * @property-read User|null $cerrador
 */
class TurnoCaja extends ModeloBase
{
    protected $table = 'turnos_caja';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'abierto_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'fondo_inicial' => 'integer',
            'esperado' => 'integer',
            'contado' => 'integer',
            'diferencia' => 'integer',
            'resumen' => 'array',
        ];
    }

    public function estaAbierto(): bool
    {
        return $this->cerrado_en === null;
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Caja, $this> */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(Caja::class);
    }

    /** @return BelongsTo<User, $this> */
    public function abridor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'abierto_por');
    }

    /** @return BelongsTo<User, $this> */
    public function cerrador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
