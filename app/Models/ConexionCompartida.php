<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una instalación a la que esta le comparte una propiedad, de solo lectura (D-073).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property string $token_hash
 * @property bool $incluir_documentos
 * @property Carbon|null $ultimo_uso_en
 * @property Carbon|null $revocada_en
 * @property Carbon|null $creado_en
 * @property-read Propiedad $propiedad
 */
class ConexionCompartida extends ModeloBase
{
    protected $table = 'conexiones_compartidas';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'incluir_documentos' => 'boolean',
            'ultimo_uso_en' => 'datetime',
            'revocada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
