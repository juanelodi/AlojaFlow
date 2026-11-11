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
 * La foto de un día cerrado: pendientes, caja por método y ocupación.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property \Carbon\CarbonImmutable $fecha
 * @property int|null $cerrado_por
 * @property Carbon $cerrado_en
 * @property string|null $notas
 * @property array<string, mixed>|null $resumen
 * @property User|null $usuario
 */
class CierreDia extends ModeloBase
{
    protected $table = 'cierres_dia';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fecha' => 'immutable_date',
            'cerrado_en' => 'datetime',
            'resumen' => 'array',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }
}
