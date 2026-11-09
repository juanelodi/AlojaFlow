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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property int $camas_extra
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
            'camas_extra' => 'integer',
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

    /**
     * Cuántas personas caben a lo sumo: las normales y las camas extra (D-125). Cero: la principal no lo dijo.
     */
    public function capacidadMaxima(): int
    {
        return $this->capacidad > 0 ? $this->capacidad + $this->camas_extra : 0;
    }

    /**
     * Con qué más se vende, además de la principal (D-118).
     *
     * @return BelongsToMany<Tarifa, $this>
     */
    public function otrasTarifas(): BelongsToMany
    {
        return $this->belongsToMany(Tarifa::class, 'tarifa_unidad');
    }

    /**
     * Todas las tarifas con que se vende, la principal primero.
     *
     * @return list<int>
     */
    public function tarifasIds(): array
    {
        $otras = $this->otrasTarifas->map(fn (Tarifa $tarifa): int => $tarifa->id)->all();

        return array_values(array_unique($this->tarifa_id === null ? $otras : [$this->tarifa_id, ...$otras]));
    }

    /**
     * De qué otras se compone, como la casa completa y sus cuartos (D-155).
     *
     * @return BelongsToMany<Unidad, $this>
     */
    public function partes(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'unidades_partes', 'unidad_id', 'parte_id');
    }

    /**
     * Las que la incluyen (D-155).
     *
     * @return BelongsToMany<Unidad, $this>
     */
    public function contenedores(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'unidades_partes', 'parte_id', 'unidad_id');
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
