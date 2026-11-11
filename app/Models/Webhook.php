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
 * Aviso firmado a otro sistema cuando cambia una reserva.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $url
 * @property string $secreto
 * @property list<string> $eventos
 * @property bool $activo
 */
class Webhook extends ModeloBase
{
    protected $table = 'webhooks';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // La dirección de un aviso suele llevar un token (Slack, Zapier…).
            'url' => 'encrypted',
            'secreto' => 'encrypted',
            'eventos' => 'array',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
