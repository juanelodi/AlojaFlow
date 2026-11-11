<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Código de un solo uso. Solo se guardan huellas: ni el código ni el destino en claro.
 *
 * @property int $id
 * @property string $proposito
 * @property string $canal
 * @property string $destino_hmac
 * @property string $codigo_hash
 * @property int $intentos
 * @property Carbon $expira_en
 * @property Carbon|null $verificado_en
 */
class CodigoOtp extends ModeloBase
{
    protected $table = 'codigos_otp';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'intentos' => 'integer',
            'expira_en' => 'datetime',
            'verificado_en' => 'datetime',
        ];
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function objeto(): MorphTo
    {
        return $this->morphTo();
    }
}
