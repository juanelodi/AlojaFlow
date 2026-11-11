<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoGrupo;
use App\Enums\EstadoReserva;
use App\Enums\ModoPrecioGrupo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Varias reservas de un mismo viaje o evento, con un coordinador.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string $nombre
 * @property EstadoGrupo $estado
 * @property string|null $color
 * @property int|null $coordinador_id
 * @property string|null $notas
 * @property ModoPrecioGrupo $modo_precio
 * @property int|null $precio_total
 * @property array<int|string, int>|null $precios_capacidad
 * @property int|null $gratis_cada
 * @property string $descuento_porcentaje
 * @property int $extras
 * @property CarbonImmutable|null $fecha_limite_lista
 * @property bool $liberar_sin_responsable
 * @property bool $acceso_activo
 * @property string|null $clave_hash
 * @property string|null $clave_cifrada
 * @property bool $debe_cambiar_clave
 * @property int $intentos_fallidos
 * @property Carbon|null $bloqueado_hasta
 * @property int $dias_acceso_tras_salida
 * @property Carbon|null $ultimo_acceso_en
 * @property-read Propiedad $propiedad
 * @property-read Huesped|null $coordinador
 */
class Grupo extends ModeloBase
{
    protected $table = 'grupos';

    protected $guarded = ['id'];

    // La clave nunca sale en arreglos, JSON ni registros.
    protected $hidden = ['clave_hash', 'clave_cifrada'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoGrupo::class,
            'modo_precio' => ModoPrecioGrupo::class,
            'precio_total' => 'integer',
            'precios_capacidad' => 'array',
            'gratis_cada' => 'integer',
            'descuento_porcentaje' => 'decimal:2',
            'extras' => 'integer',
            'fecha_limite_lista' => 'immutable_date',
            'liberar_sin_responsable' => 'boolean',
            'acceso_activo' => 'boolean',
            'clave_cifrada' => 'encrypted',
            'debe_cambiar_clave' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'dias_acceso_tras_salida' => 'integer',
            'ultimo_acceso_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Huesped, $this> */
    public function coordinador(): BelongsTo
    {
        return $this->belongsTo(Huesped::class, 'coordinador_id');
    }

    /** @return HasMany<Reserva, $this> */
    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    /** @return HasMany<CuotaGrupo, $this> */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaGrupo::class);
    }

    /** @return HasMany<ComprobantePago, $this> */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(ComprobantePago::class);
    }

    /** @return HasMany<MensajeGrupo, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeGrupo::class);
    }

    public function primeraLlegada(): ?CarbonImmutable
    {
        $fecha = $this->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->min('llegada');

        return $fecha === null ? null : CarbonImmutable::parse((string) $fecha)->startOfDay();
    }

    public function ultimaSalida(): ?CarbonImmutable
    {
        $fecha = $this->reservas()->where('estado', '!=', EstadoReserva::Cancelada->value)->max('salida');

        return $fecha === null ? null : CarbonImmutable::parse((string) $fecha)->startOfDay();
    }
}
