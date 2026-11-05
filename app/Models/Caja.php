<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\ProtegidaPorSincronizacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Caja o cuenta donde entra y sale el dinero.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property bool $sincronizada
 * @property int|null $principal_id
 * @property string|null $clave_origen
 * @property string $nombre
 * @property bool $activa
 */
class Caja extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'cajas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'activa' => 'boolean',
            'sincronizada' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
