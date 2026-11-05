<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoFactura;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Un pago previsto de una estancia larga. Si está pagado o no se calcula: lo pagado se aplica a los primeros en vencer (`Mensualidades`).
 *
 * @property int $id
 * @property int $reserva_id
 * @property string|null $concepto
 * @property CarbonImmutable|null $desde
 * @property CarbonImmutable|null $hasta
 * @property int $importe
 * @property CarbonImmutable $vence_en
 * @property \Illuminate\Support\Carbon|null $creado_en
 * @property-read Reserva $reserva
 * @property-read Factura|null $facturaVigente
 */
class CuotaReserva extends ModeloBase
{
    protected $table = 'cuotas_reserva';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer', 'desde' => 'immutable_date', 'hasta' => 'immutable_date', 'vence_en' => 'immutable_date'];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /**
     * Una factura cancelada no cuenta: la cuota se puede volver a facturar.
     *
     * @return HasOne<Factura, $this>
     */
    public function facturaVigente(): HasOne
    {
        return $this->hasOne(Factura::class, 'cuota_reserva_id')->where('estado', '!=', EstadoFactura::Cancelada->value);
    }

    /**
     * Lo que se le dice a cada quien que es este pago: lo que alguien escribió o, si no, las fechas que cubre, en su idioma.
     */
    public function periodo(?string $idioma = null): string
    {
        if (filled($this->concepto)) {
            return (string) $this->concepto;
        }
        $idioma ??= app()->getLocale();

        return __('estancias.cuotas.periodo', [
            'desde' => (string) $this->desde?->locale($idioma)->isoFormat('ll'),
            'hasta' => (string) $this->hasta?->locale($idioma)->isoFormat('ll'),
        ], $idioma);
    }
}
