<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\FrecuenciaLimpieza;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Limpieza fija durante la estancia de una reserva.
 *
 * @property int $id
 * @property int $reserva_id
 * @property FrecuenciaLimpieza $frecuencia
 * @property int|null $dias_semana
 * @property list<string>|null $fechas
 * @property int|null $creada_por
 */
class ReglaLimpieza extends ModeloBase
{
    protected $table = 'reglas_limpieza';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'frecuencia' => FrecuenciaLimpieza::class,
            'dias_semana' => 'integer',
            'fechas' => 'array',
        ];
    }

    /** @return BelongsTo<Reserva, $this> */
    public function reserva(): BelongsTo
    {
        return $this->belongsTo(Reserva::class);
    }

    public function tocaEl(CarbonInterface $fecha): bool
    {
        return match ($this->frecuencia) {
            FrecuenciaLimpieza::Diaria => true,
            FrecuenciaLimpieza::Semanal => ((int) $this->dias_semana & (1 << ($fecha->dayOfWeekIso - 1))) !== 0,
            FrecuenciaLimpieza::Fechas => in_array($fecha->format('Y-m-d'), (array) $this->fechas, true),
        };
    }
}
