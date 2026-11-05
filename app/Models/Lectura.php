<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ServicioMedido;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que marcó un medidor un día. Lo cobrado se calcula al capturarla y no cambia si mañana cambia la tarifa.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property int|null $reserva_id
 * @property int|null $cargo_id
 * @property ServicioMedido $servicio
 * @property CarbonImmutable $leida_en
 * @property string $actual
 * @property string $consumo
 * @property string|null $tarifa
 * @property int $importe
 * @property bool $inicial
 * @property-read Unidad $unidad
 * @property-read Reserva|null $reserva
 * @property-read CargoReserva|null $cargo
 */
class Lectura extends ModeloBase
{
    protected $table = 'lecturas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'servicio' => ServicioMedido::class,
            'leida_en' => 'immutable_date',
            'actual' => 'decimal:2',
            'consumo' => 'decimal:2',
            'tarifa' => 'decimal:4',
            'importe' => 'integer',
            'inicial' => 'boolean',
        ];
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<CargoReserva, $this> */
    public function cargo(): BelongsTo
    {
        return $this->belongsTo(CargoReserva::class, 'cargo_id');
    }
}
