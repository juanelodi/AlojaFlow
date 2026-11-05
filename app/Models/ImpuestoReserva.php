<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que se cobró de un impuesto en una reserva. Es una foto: si mañana cambia la tasa, lo cobrado no cambia (D-082).
 *
 * @property int $id
 * @property int $reserva_id
 * @property int|null $impuesto_id
 * @property string $nombre
 * @property int $importe
 * @property bool $incluido
 * @property bool $cobrado_por_canal
 */
class ImpuestoReserva extends Model
{
    public $timestamps = false;

    protected $table = 'reservas_impuestos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer', 'incluido' => 'boolean', 'cobrado_por_canal' => 'boolean'];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
