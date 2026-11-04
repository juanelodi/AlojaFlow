<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
