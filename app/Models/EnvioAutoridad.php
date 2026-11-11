<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoEnvioAutoridad;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que se comunicó, o falta comunicar, de una reserva al registro de viajeros (D-100). **Aquí no se guarda lo que se mandó:** se arma cada vez de la reserva,
 * para no tener otra copia de los documentos de la gente. Solo lo que alguien corrigió a mano para este envío, cifrado.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $reserva_id
 * @property string $servicio
 * @property string $tipo
 * @property EstadoEnvioAutoridad $estado
 * @property string|null $huella
 * @property string|null $lote
 * @property int|null $orden
 * @property string|null $codigo_comunicacion
 * @property string|null $anulacion_lote
 * @property list<array{persona: string, campo: string}>|null $faltan
 * @property array<string, array<string, string>>|null $correcciones
 * @property string|null $ultimo_error
 * @property int $intentos
 * @property Carbon|null $proximo_intento_en
 * @property Carbon|null $vence_en
 * @property Carbon|null $enviado_en
 * @property Carbon|null $respondido_en
 * @property-read Propiedad $propiedad
 * @property-read Reserva|null $reserva
 */
class EnvioAutoridad extends ModeloBase
{
    public const RESERVA = 'RH';

    public const PARTE = 'PV';

    protected $table = 'envios_autoridad';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoEnvioAutoridad::class,
            'faltan' => 'array',
            'correcciones' => 'encrypted:array',
            'ultimo_error' => 'encrypted',
            'orden' => 'integer',
            'intentos' => 'integer',
            'proximo_intento_en' => 'datetime',
            'vence_en' => 'datetime',
            'enviado_en' => 'datetime',
            'respondido_en' => 'datetime',
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
}
