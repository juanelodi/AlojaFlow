<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una foto de la página pública: de la propiedad, o de una tarifa si la tiene (D-104).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $tarifa_id
 * @property int|null $archivo_id
 * @property int|null $miniatura_id
 * @property string|null $texto_alternativo
 * @property int $orden
 * @property-read Propiedad $propiedad
 * @property-read Tarifa|null $tarifa
 * @property-read Archivo|null $archivo
 * @property-read Archivo|null $miniatura
 */
class Foto extends ModeloBase
{
    protected $table = 'fotos';

    protected $guarded = ['id'];

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Tarifa, $this> */
    public function tarifa(): BelongsTo
    {
        return $this->belongsTo(Tarifa::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function archivo(): BelongsTo
    {
        return $this->belongsTo(Archivo::class);
    }

    /** @return BelongsTo<Archivo, $this> */
    public function miniatura(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'miniatura_id');
    }

    public function url(string $tamano = 'grande'): string
    {
        return route('fotos.ver', ['foto' => $this->id, 'tamano' => $tamano]);
    }

    public function textoPara(string $nombre): string
    {
        return filled($this->texto_alternativo) ? (string) $this->texto_alternativo : __('portal.foto_de', ['nombre' => $nombre]);
    }
}
