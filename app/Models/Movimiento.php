<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MetodoPago;
use App\Enums\TipoMovimiento;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Gasto o ingreso que no es de una reserva (modelo-datos.md §4.4).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property bool $sincronizada
 * @property int|null $principal_id
 * @property string|null $clave_origen
 * @property int|null $caja_id
 * @property CarbonImmutable $fecha
 * @property TipoMovimiento $tipo
 * @property string|null $categoria
 * @property string $concepto
 * @property MetodoPago $metodo
 * @property int $importe
 * @property string $moneda
 * @property string|null $referencia
 * @property int|null $user_id
 * @property-read Propiedad $propiedad
 */
class Movimiento extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'movimientos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'fecha' => 'immutable_date',
            'tipo' => TipoMovimiento::class,
            'metodo' => MetodoPago::class,
            'importe' => 'integer',
            'sincronizada' => 'boolean',
        ];
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
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
