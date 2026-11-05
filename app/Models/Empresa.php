<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoEmpresa;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una empresa o agencia que reserva o paga por sus huéspedes (D-081).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property TipoEmpresa $tipo
 * @property string $nombre
 * @property string|null $identificador_fiscal
 * @property string|null $contacto
 * @property string|null $correo
 * @property string|null $telefono
 * @property string|null $comision_porcentaje
 * @property int|null $dias_credito
 * @property string|null $condiciones
 * @property bool $activa
 * @property-read Propiedad $propiedad
 */
class Empresa extends ModeloBase
{
    protected $table = 'empresas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoEmpresa::class,
            'comision_porcentaje' => 'decimal:4',
            'dias_credito' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }
}
