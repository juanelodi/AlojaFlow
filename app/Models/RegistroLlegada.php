<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoRegistroLlegada;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Los datos que manda el huésped antes de llegar.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $reserva_id
 * @property EstadoRegistroLlegada $estado
 * @property string|null $idioma
 * @property string|null $hora_llegada_estimada
 * @property string|null $notas_huesped
 * @property list<int>|null $documentos_marcados
 * @property Carbon|null $recibido_en
 * @property int|null $revisado_por
 * @property Carbon|null $revisado_en
 * @property \Illuminate\Support\Carbon|null $conservar_hasta
 * @property Reserva $reserva
 * @property Propiedad $propiedad
 * @property \Illuminate\Database\Eloquent\Collection<int, PersonaLlegada> $personas
 */
class RegistroLlegada extends ModeloBase
{
    protected $table = 'registros_llegada';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoRegistroLlegada::class,
            'documentos_marcados' => 'array',
            'recibido_en' => 'datetime',
            'revisado_en' => 'datetime',
            'conservar_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    /** @return HasMany<PersonaLlegada, $this> */
    public function personas(): HasMany
    {
        return $this->hasMany(PersonaLlegada::class)->orderBy('orden');
    }

    /** @return HasMany<Aceptacion, $this> */
    public function aceptaciones(): HasMany
    {
        return $this->hasMany(Aceptacion::class);
    }

    /** @return MorphMany<Archivo, $this> */
    public function archivos(): MorphMany
    {
        return $this->morphMany(Archivo::class, 'objeto');
    }
}
