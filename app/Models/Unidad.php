<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrigenFila;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Habitación, cabaña, departamento o parcela (proyección; modelo-datos.md §4.2).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $tarifa_id
 * @property int|null $propietario_id
 * @property string|null $comision_propietario
 * @property string $codigo
 * @property string $nombre
 * @property string|null $piso
 * @property string|null $edificio
 * @property int $capacidad
 * @property array<string, mixed>|null $atributos
 * @property int $orden
 * @property bool $activa
 * @property OrigenFila $origen
 * @property bool $sincronizada
 * @property-read Propiedad $propiedad
 * @property-read Tarifa|null $tarifa
 */
class Unidad extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'unidades';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'atributos' => 'array',
            'comision_propietario' => 'decimal:2',
            'origen' => OrigenFila::class,
            'sincronizada' => 'boolean',
        ];
    }

    /**
     * Lo que dice de sí un lugar de un camping (D-110).
     */
    public function atributo(string $clave): mixed
    {
        return ((array) $this->atributos)[$clave] ?? null;
    }

    /** @return HasOne<EstadoUnidad, $this> */
    public function estado(): HasOne
    {
        return $this->hasOne(EstadoUnidad::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Propietario, $this> */
    public function propietario(): BelongsTo
    {
        return $this->belongsTo(Propietario::class);
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
