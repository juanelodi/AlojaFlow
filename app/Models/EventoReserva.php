<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEventoReserva;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Cambio detectado en una reserva al comparar sincronizaciones (modelo-datos.md §4.8).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property TipoEventoReserva $tipo
 * @property array<string, mixed>|null $datos
 * @property Carbon $ocurrido_en
 * @property Carbon|null $procesado_en
 * @property-read Reserva $reserva
 */
class EventoReserva extends ModeloBase
{
    protected $table = 'eventos_reserva';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEventoReserva::class,
            'datos' => 'array',
            'ocurrido_en' => 'datetime',
            'procesado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }
}
