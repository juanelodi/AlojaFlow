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
 * Lo que pasó con una factura ante la autoridad. Sin secretos.
 *
 * @property int $id
 * @property int $factura_id
 * @property string $tipo
 * @property string $estado
 * @property array<string, mixed>|null $respuesta
 * @property int $intentos
 */
class EventoFiscal extends ModeloBase
{
    protected $table = 'eventos_fiscales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'respuesta' => 'array',
            'intentos' => 'integer',
        ];
    }

    /** @return BelongsTo<Factura, $this> */
    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
