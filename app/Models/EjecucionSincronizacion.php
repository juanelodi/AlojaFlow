<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEjecucion;
use App\Enums\OrigenEjecucion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $principal_id
 * @property OrigenEjecucion $origen
 * @property EstadoEjecucion $estado
 * @property Carbon $iniciada_en
 * @property Carbon|null $terminada_en
 * @property string|null $paso_fallido
 * @property string|null $codigo_error
 * @property string|null $detalle
 * @property int|null $bytes_descargados
 * @property int|null $bytes_descomprimidos
 * @property string|null $version_esquema
 * @property Carbon|null $fecha_copia
 * @property array<string, int>|null $filas
 * @property int $eventos
 * @property array<string, int>|null $incidencias
 * @property-read Principal $principal
 */
class EjecucionSincronizacion extends ModeloBase
{
    protected $table = 'ejecuciones_sincronizacion';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'origen' => OrigenEjecucion::class,
            'estado' => EstadoEjecucion::class,
            'iniciada_en' => 'datetime',
            'terminada_en' => 'datetime',
            'fecha_copia' => 'datetime',
            'filas' => 'array',
            'incidencias' => 'array',
        ];
    }

    /** @return BelongsTo<Principal, $this> */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    public function duracionSegundos(): ?int
    {
        return $this->terminada_en ? (int) $this->iniciada_en->diffInSeconds($this->terminada_en) : null;
    }
}
