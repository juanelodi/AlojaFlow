<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Support\Huellas;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una persona del registro de llegada. El número de documento va cifrado.
 *
 * @property int $id
 * @property int $registro_llegada_id
 * @property int $orden
 * @property bool $es_titular
 * @property string $nombre
 * @property string|null $apellido1
 * @property string|null $apellido2
 * @property string|null $sexo
 * @property \Illuminate\Support\Carbon|null $fecha_nacimiento
 * @property string|null $nacionalidad_texto
 * @property string|null $documento_tipo
 * @property string|null $documento_numero
 * @property \Illuminate\Support\Carbon|null $documento_vencimiento
 * @property string|null $domicilio_calle
 * @property string|null $domicilio_ciudad
 * @property string|null $domicilio_region
 * @property string|null $domicilio_codigo_postal
 * @property string|null $domicilio_pais_texto
 * @property string|null $correo
 * @property string|null $telefono
 * @property array<string, mixed>|null $datos_pais
 */
class PersonaLlegada extends ModeloBase
{
    protected $table = 'registros_llegada_personas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'es_titular' => 'boolean',
            'fecha_nacimiento' => 'date',
            'documento_numero' => 'encrypted',
            'documento_vencimiento' => 'date',
            'datos_pais' => 'array',
        ];
    }

    /** @return BelongsTo<RegistroLlegada, $this> */
    public function registro(): BelongsTo
    {
        return $this->belongsTo(RegistroLlegada::class, 'registro_llegada_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $persona): void {
            if ($persona->isDirty('documento_numero')) {
                $numero = $persona->documento_numero;
                $persona->setAttribute('documento_numero_hmac', blank($numero) ? null : Huellas::hmacDocumento((string) $numero));
            }
        });
    }

    public function nombreCompleto(): string
    {
        return trim(implode(' ', array_filter([$this->nombre, $this->apellido1, $this->apellido2], fn (?string $p): bool => filled($p))));
    }
}
