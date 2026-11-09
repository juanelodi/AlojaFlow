<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\CanalEnvio;
use App\Enums\EstadoEnvio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un mensaje en la cola o en el historial. La clave impide mandar dos veces lo mismo.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $reserva_id
 * @property int|null $huesped_id
 * @property int|null $automatizacion_id
 * @property int|null $webhook_id
 * @property CanalEnvio $canal
 * @property string|null $destino
 * @property string|null $idioma
 * @property string|null $asunto
 * @property string|null $cuerpo
 * @property array<string, mixed>|null $datos
 * @property EstadoEnvio $estado
 * @property int $intentos
 * @property string|null $ultimo_error
 * @property Carbon|null $programado_para
 * @property Carbon|null $enviado_en
 * @property string|null $clave_idempotencia
 * @property Carbon|null $creado_en
 * @property Reserva|null $reserva
 * @property Webhook|null $webhook
 * @property Propiedad $propiedad
 * @property Automatizacion|null $automatizacion
 */
class Envio extends ModeloBase
{
    protected $table = 'envios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'canal' => CanalEnvio::class,
            'estado' => EstadoEnvio::class,
            'intentos' => 'integer',
            'datos' => 'array',
            'programado_para' => 'datetime',
            'enviado_en' => 'datetime',
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

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }

    /** @return BelongsTo<Automatizacion, $this> */
    public function automatizacion(): BelongsTo
    {
        return $this->belongsTo(Automatizacion::class);
    }

    /** @return BelongsTo<Webhook, $this> */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}
