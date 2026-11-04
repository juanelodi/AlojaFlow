<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoPropiedad;
use App\Enums\PaquetePais;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $nombre
 * @property string $codigo
 * @property string $color
 * @property ModoPropiedad $modo
 * @property PaquetePais $paquete_pais
 * @property string|null $pais
 * @property string $zona_horaria
 * @property string $moneda
 * @property string $idioma
 * @property bool $activa
 * @property bool $es_demo
 * @property int $consecutivo_reserva
 * @property-read Principal|null $principal
 */
class Propiedad extends ModeloBase
{
    protected $table = 'propiedades';

    protected $fillable = [
        'nombre', 'codigo', 'color', 'modo', 'paquete_pais', 'pais', 'subdivision',
        'zona_horaria', 'moneda', 'idioma', 'domicilio', 'telefono', 'correo', 'ajustes', 'activa', 'es_demo',
    ];

    protected function casts(): array
    {
        return [
            'modo' => ModoPropiedad::class,
            'paquete_pais' => PaquetePais::class,
            'ajustes' => 'array',
            'activa' => 'boolean',
            'es_demo' => 'boolean',
            'consecutivo_reserva' => 'integer',
        ];
    }

    /** @return HasOne<Principal, $this> */
    public function principal(): HasOne
    {
        return $this->hasOne(Principal::class);
    }

    /** @return HasMany<Unidad, $this> */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    /** @return HasMany<Huesped, $this> */
    public function huespedes(): HasMany
    {
        return $this->hasMany(Huesped::class);
    }

    /** @return HasMany<Tarifa, $this> */
    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class);
    }

    /** @return HasMany<Aviso, $this> */
    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class);
    }

    /** @return HasManyThrough<EjecucionSincronizacion, Principal, $this> */
    public function ejecuciones(): HasManyThrough
    {
        return $this->hasManyThrough(EjecucionSincronizacion::class, Principal::class);
    }

    /**
     * Sus reservas y catálogos los gestiona AlojaFlow, sin una principal (D-035).
     */
    public function esIndependiente(): bool
    {
        return $this->modo === ModoPropiedad::Nativa;
    }

    /**
     * La fecha operativa: qué día es «hoy» en el alojamiento (modelo-datos.md §1.4).
     */
    public function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now($this->zona_horaria)->startOfDay();
    }
}
