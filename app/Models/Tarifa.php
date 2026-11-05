<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoTarifa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Precio por noche de una propiedad independiente, con temporadas que lo sustituyen (D-036). Puede salir de otra tarifa, con un ajuste (D-119).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $tarifa_base_id
 * @property string|null $ajuste_porcentaje
 * @property int|null $ajuste_noche
 * @property string $nombre
 * @property ModoTarifa $modo
 * @property int $precio_noche
 * @property int $personas_incluidas
 * @property int $precio_persona_extra
 * @property int|null $precio_fin_semana
 * @property int|null $precio_semana
 * @property int|null $precio_mes
 * @property int $estancia_minima
 * @property int|null $personas_minimas
 * @property int|null $personas_maximas
 * @property int|null $politica_id
 * @property string|null $descripcion_publica
 * @property bool $visible_motor
 * @property bool $activa
 * @property-read Propiedad $propiedad
 * @property-read Tarifa|null $base
 * @property-read Politica|null $politica
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TemporadaTarifa> $temporadas
 */
class Tarifa extends ModeloBase
{
    protected $table = 'tarifas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'modo' => ModoTarifa::class,
            'ajuste_porcentaje' => 'decimal:2',
            'ajuste_noche' => 'integer',
            'personas_minimas' => 'integer',
            'personas_maximas' => 'integer',
            'precio_noche' => 'integer',
            'personas_incluidas' => 'integer',
            'precio_persona_extra' => 'integer',
            'precio_fin_semana' => 'integer',
            'precio_semana' => 'integer',
            'precio_mes' => 'integer',
            'estancia_minima' => 'integer',
            'visible_motor' => 'boolean',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<TemporadaTarifa, $this> */
    public function temporadas(): HasMany
    {
        return $this->hasMany(TemporadaTarifa::class)->orderBy('desde');
    }

    /** @return HasMany<Unidad, $this> */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    /**
     * Las que también la venden, además de las que la tienen como principal (D-118).
     *
     * @return BelongsToMany<Unidad, $this>
     */
    public function otrasUnidades(): BelongsToMany
    {
        return $this->belongsToMany(Unidad::class, 'tarifa_unidad');
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function base(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class, 'tarifa_base_id');
    }

    /** @return HasMany<Tarifa, $this> */
    public function derivadas(): HasMany
    {
        return $this->hasMany(Tarifa::class, 'tarifa_base_id');
    }

    /** @return BelongsTo<Politica, $this> */
    public function politica(): BelongsTo
    {
        return $this->belongsTo(Politica::class);
    }

    /**
     * De dónde salen sus precios y temporadas.
     */
    public function precios(): Tarifa
    {
        return $this->base ?? $this;
    }

    /**
     * El precio de una noche con el ajuste de la que sale de otra. Nunca negativo.
     */
    public function conAjuste(float $precio): float
    {
        return $this->tarifa_base_id === null ? $precio : max(0.0, $precio * (1 + (float) $this->ajuste_porcentaje / 100) + (int) $this->ajuste_noche);
    }

    public function admitePersonas(int $personas): bool
    {
        return ($this->personas_minimas === null || $personas >= $this->personas_minimas)
            && ($this->personas_maximas === null || $personas <= $this->personas_maximas);
    }

    /**
     * Las condiciones con que se vende: las suyas o las de la tarifa de la que sale. Sin ninguna, las generales.
     */
    public function politicaPropia(): ?Politica
    {
        return $this->politica ?? $this->base?->politica;
    }
}
