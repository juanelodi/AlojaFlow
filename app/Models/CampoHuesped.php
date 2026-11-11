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
 * Campo personalizado del huésped en la principal (relclienti, tipo campo_pers).
 *
 * @property int $id
 * @property int $huesped_id
 * @property string $campo
 * @property string|null $valor
 */
class CampoHuesped extends ModeloBase
{
    protected $table = 'campos_huesped';

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }
}
