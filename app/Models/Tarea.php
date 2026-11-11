<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoTarea;
use App\Enums\TipoTarea;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Limpieza, inspección o mantenimiento por hacer.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $unidad_id
 * @property int|null $reserva_id
 * @property TipoTarea $tipo
 * @property string $titulo
 * @property string|null $descripcion
 * @property list<array{texto: string, hecho: bool}>|null $puntos
 * @property CarbonImmutable $fecha
 * @property int $prioridad
 * @property EstadoTarea $estado
 * @property int|null $asignada_a
 * @property int|null $creada_por
 * @property int|null $hecha_por
 * @property \Illuminate\Support\Carbon|null $hecha_en
 * @property string|null $clave_generacion
 * @property Unidad|null $unidad
 * @property Propiedad $propiedad
 * @property User|null $asignada
 */
class Tarea extends ModeloBase
{
    protected $table = 'tareas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoTarea::class,
            'estado' => EstadoTarea::class,
            'fecha' => 'immutable_date',
            'prioridad' => 'integer',
            'puntos' => 'array',
            'hecha_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
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

    /** @return BelongsTo<User, $this> */
    public function asignada(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignada_a');
    }

    /** @return MorphMany<Archivo, $this> */
    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'objeto');
    }

    public function abierta(): bool
    {
        return in_array($this->estado, [EstadoTarea::Pendiente, EstadoTarea::EnCurso], true);
    }
}
