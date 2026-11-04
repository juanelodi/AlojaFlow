<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cobro o devolución (importe negativo) de una reserva de una propiedad independiente.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property CarbonImmutable $fecha
 * @property MetodoPago $metodo
 * @property int $importe
 * @property string $moneda
 * @property string|null $referencia
 * @property int|null $user_id
 * @property-read Reserva $reserva
 * @property-read User|null $usuario
 */
class Pago extends ModeloBase
{
    protected $table = 'pagos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'immutable_date',
            'metodo' => MetodoPago::class,
            'importe' => 'integer',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
