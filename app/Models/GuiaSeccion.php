<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\MomentoGuia;
use App\Enums\TipoSeccionGuia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una parte de la guía digital, en los idiomas que se hayan escrito (D-106).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $unidad_id
 * @property TipoSeccionGuia $tipo
 * @property MomentoGuia $momento
 * @property bool $solo_con_reserva
 * @property bool $requiere_pago
 * @property array<string, string|null> $titulo
 * @property array<string, string|null> $cuerpo
 * @property int $orden
 * @property bool $activa
 * @property-read Propiedad $propiedad
 * @property-read Unidad|null $unidad
 */
class GuiaSeccion extends ModeloBase
{
    protected $table = 'guia_secciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoSeccionGuia::class,
            'momento' => MomentoGuia::class,
            'solo_con_reserva' => 'boolean',
            'requiere_pago' => 'boolean',
            'titulo' => 'array',
            'cuerpo' => 'array',
            'orden' => 'integer',
            'activa' => 'boolean',
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
}
