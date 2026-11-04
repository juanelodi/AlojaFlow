<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoReserva;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué mensaje se manda, cuándo y a quién.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property DisparadorAutomatizacion $disparador
 * @property int $dias
 * @property string|null $hora
 * @property array<string, mixed>|null $condiciones
 * @property AccionAutomatizacion $accion
 * @property int $plantilla_id
 * @property string|null $destino
 * @property bool $activa
 * @property \Illuminate\Support\Carbon|null $creado_en
 * @property Plantilla $plantilla
 * @property Propiedad $propiedad
 */
class Automatizacion extends ModeloBase
{
    protected $table = 'automatizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'disparador' => DisparadorAutomatizacion::class,
            'accion' => AccionAutomatizacion::class,
            'dias' => 'integer',
            'condiciones' => 'array',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Plantilla, $this> */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    public function cumpleCondiciones(Reserva $reserva): bool
    {
        $estado = EstadoReserva::tryFrom((string) (((array) $this->condiciones)['estado'] ?? ''));

        return $estado === null || $reserva->estado === $estado;
    }
}
