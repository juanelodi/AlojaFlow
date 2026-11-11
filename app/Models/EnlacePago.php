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
 * Un enlace para cobrar lo que se decida de una reserva: un anticipo, un extra, un daño (D-085).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property int|null $enlace_publico_id
 * @property string $concepto
 * @property int $importe
 * @property string $moneda
 * @property int|null $caja_id
 * @property Carbon $vence_en
 * @property Carbon|null $pagado_en
 * @property int|null $pago_id
 * @property Carbon|null $anulado_en
 * @property int|null $creado_por
 * @property-read Reserva $reserva
 * @property-read Propiedad $propiedad
 * @property-read EnlacePublico|null $enlacePublico
 */
class EnlacePago extends ModeloBase
{
    protected $table = 'enlaces_pago';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer', 'vence_en' => 'datetime', 'pagado_en' => 'datetime', 'anulado_en' => 'datetime'];
    }

    public function estado(): string
    {
        return match (true) {
            $this->pagado_en !== null => 'pagado',
            $this->anulado_en !== null => 'anulado',
            $this->vence_en->isPast() => 'vencido',
            default => 'vigente',
        };
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<EnlacePublico, $this> */
    public function enlacePublico(): BelongsTo
    {
        return $this->belongsTo(EnlacePublico::class);
    }
}
