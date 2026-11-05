<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoComprobante;
use App\Enums\MetodoPago;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un pago que alguien dice haber hecho, de un grupo o de una reserva (D-097). Hasta que se confirma no cuenta en ningún saldo.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $grupo_id
 * @property int|null $reserva_id
 * @property int $importe
 * @property string $moneda
 * @property CarbonImmutable $fecha
 * @property MetodoPago $metodo
 * @property string|null $referencia
 * @property int|null $archivo_id
 * @property EstadoComprobante $estado
 * @property string|null $motivo_rechazo
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property-read Propiedad $propiedad
 * @property-read Grupo|null $grupo
 * @property-read Reserva|null $reserva
 * @property-read Archivo|null $archivo
 * @property-read User|null $revisor
 */
class ComprobantePago extends ModeloBase
{
    protected $table = 'comprobantes_pago';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'importe' => 'integer',
            'fecha' => 'immutable_date',
            'metodo' => MetodoPago::class,
            'estado' => EstadoComprobante::class,
            'revisado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function revisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
