<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoSolicitudPrivacidad;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Constancia de que se exportaron o anonimizaron los datos de un huésped.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $huesped_id
 * @property TipoSolicitudPrivacidad $tipo
 * @property int|null $solicitada_por
 * @property string $estado
 * @property Carbon|null $completada_en
 * @property int|null $archivo_id
 * @property Archivo|null $archivo
 */
class SolicitudPrivacidad extends ModeloBase
{
    protected $table = 'solicitudes_privacidad';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoSolicitudPrivacidad::class,
            'completada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }
}
