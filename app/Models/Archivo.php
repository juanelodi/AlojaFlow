<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClaseArchivo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un archivo subido o generado. Vive fuera de la carpeta pública y se sirve solo tras comprobar permisos.
 *
 * @property int $id
 * @property int|null $propiedad_id
 * @property string $ruta
 * @property string $nombre_original
 * @property string $tipo_mime
 * @property int $bytes
 * @property string $sha256
 * @property bool $cifrado
 * @property ClaseArchivo $clase
 * @property int|null $subido_por
 * @property \Illuminate\Support\Carbon|null $conservar_hasta
 */
class Archivo extends ModeloBase
{
    protected $table = 'archivos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bytes' => 'integer',
            'cifrado' => 'boolean',
            'clase' => ClaseArchivo::class,
            'conservar_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, $this> */
    public function objeto(): MorphTo
    {
        return $this->morphTo();
    }
}
