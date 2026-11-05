<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * El dueño de unas unidades que administra esta propiedad, con su acuerdo (D-114).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property string|null $correo
 * @property string|null $telefono
 * @property string|null $identificador_fiscal
 * @property string|null $idioma
 * @property string $comision_porcentaje
 * @property bool $comision_sobre_neto
 * @property int $cargo_fijo
 * @property bool $puede_bloquear
 * @property string|null $notas
 * @property bool $activo
 * @property bool $acceso_activo
 * @property string|null $clave_hash
 * @property string|null $clave_cifrada
 * @property bool $debe_cambiar_clave
 * @property int $intentos_fallidos
 * @property Carbon|null $bloqueado_hasta
 * @property Carbon|null $ultimo_acceso_en
 * @property-read Propiedad $propiedad
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Unidad> $unidades
 */
class Propietario extends ModeloBase
{
    protected $table = 'propietarios';

    protected $guarded = ['id'];

    // La clave nunca sale en arreglos, JSON ni registros.
    protected $hidden = ['clave_hash', 'clave_cifrada'];

    // Los valores de la base, también para quien recién lo creó y no lo ha vuelto a leer.
    protected $attributes = ['comision_porcentaje' => 0, 'comision_sobre_neto' => true, 'cargo_fijo' => 0, 'puede_bloquear' => true, 'activo' => true, 'acceso_activo' => false, 'debe_cambiar_clave' => false, 'intentos_fallidos' => 0];

    protected function casts(): array
    {
        return [
            'comision_porcentaje' => 'decimal:2',
            'comision_sobre_neto' => 'boolean',
            'cargo_fijo' => 'integer',
            'puede_bloquear' => 'boolean',
            'activo' => 'boolean',
            'acceso_activo' => 'boolean',
            'clave_cifrada' => 'encrypted',
            'debe_cambiar_clave' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<Unidad, $this> */
    public function unidades(): HasMany
    {
        return $this->hasMany(Unidad::class);
    }

    /** @return HasMany<EstadoCuenta, $this> */
    public function estadosCuenta(): HasMany
    {
        return $this->hasMany(EstadoCuenta::class);
    }
}
