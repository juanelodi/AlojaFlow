<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModoFiscal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Quién emite las facturas.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property string $pais
 * @property string $identificador_fiscal
 * @property string|null $regimen_fiscal
 * @property string|null $codigo_postal
 * @property string|null $domicilio
 * @property array<string, mixed>|null $configuracion
 * @property ModoFiscal $modo
 * @property Propiedad $propiedad
 */
class EntidadFiscal extends ModeloBase
{
    protected $table = 'entidades_fiscales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'configuracion' => 'encrypted:array',
            'modo' => ModoFiscal::class,
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<SerieFacturacion, $this> */
    public function series(): HasMany
    {
        return $this->hasMany(SerieFacturacion::class);
    }
}
