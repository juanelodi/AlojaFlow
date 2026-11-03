<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoReserva;
use App\Enums\OrigenFila;
use App\Enums\TipoReserva;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Reserva o bloqueo (proyección en la fase 1; nativa en la fase 2; modelo-datos.md §4.4).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int $unidad_id
 * @property int|null $titular_id
 * @property TipoReserva $tipo
 * @property EstadoReserva $estado
 * @property string $numero
 * @property string $codigo_publico
 * @property CarbonImmutable $llegada
 * @property CarbonImmutable $salida
 * @property int $noches
 * @property int $personas
 * @property string|null $origen_texto
 * @property string|null $grupo_codigo
 * @property string|null $tarifa_nombre
 * @property string $moneda
 * @property int $total
 * @property int $pagado
 * @property int $pendiente
 * @property int $deposito
 * @property int $descuento
 * @property int $comision
 * @property Carbon|null $entrada_registrada_en
 * @property Carbon|null $salida_registrada_en
 * @property string|null $comentario
 * @property Carbon|null $creada_en_origen
 * @property Carbon|null $eliminada_en_principal_en
 * @property OrigenFila $origen
 * @property bool $sincronizada
 * @property int|null $principal_id
 * @property string|null $clave_origen
 * @property-read Propiedad $propiedad
 * @property-read Unidad $unidad
 * @property-read Huesped|null $titular
 * @property-read Principal|null $principal
 */
class Reserva extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'reservas';

    protected $guarded = ['id', 'noches', 'pendiente'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoReserva::class,
            'estado' => EstadoReserva::class,
            'llegada' => 'immutable_date',
            'salida' => 'immutable_date',
            'noches' => 'integer',
            'total' => 'integer',
            'pagado' => 'integer',
            'pendiente' => 'integer',
            'deposito' => 'integer',
            'descuento' => 'integer',
            'comision' => 'integer',
            'impuestos_porcentaje' => 'decimal:4',
            'entrada_registrada_en' => 'datetime',
            'salida_registrada_en' => 'datetime',
            'creada_en_origen' => 'datetime',
            'modificada_en_origen' => 'datetime',
            'eliminada_en_principal_en' => 'datetime',
            'origen' => OrigenFila::class,
            'sincronizada' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Unidad, $this> */
    public function unidad(): BelongsTo
    {
        return $this->belongsTo(Unidad::class);
    }

    /** @return BelongsTo<Huesped, $this> */
    public function titular(): BelongsTo
    {
        return $this->belongsTo(Huesped::class, 'titular_id');
    }

    /** @return BelongsTo<Principal, $this> */
    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    /** @return HasMany<ReservaNoche, $this> */
    public function nochesOcupadas(): HasMany
    {
        return $this->hasMany(ReservaNoche::class);
    }

    /** @return BelongsToMany<Huesped, $this> */
    public function huespedes(): BelongsToMany
    {
        return $this->belongsToMany(Huesped::class, 'reservas_huespedes')->withPivot(['orden', 'parentesco_texto']);
    }

    /** @return HasMany<EventoReserva, $this> */
    public function eventos(): HasMany
    {
        return $this->hasMany(EventoReserva::class);
    }

    /**
     * Reservas y bloqueos que ocupan: ni canceladas ni eliminadas en la principal.
     *
     * @param  Builder<Reserva>  $consulta
     */
    public function scopeVigentes(Builder $consulta): void
    {
        $consulta->where('estado', '!=', EstadoReserva::Cancelada->value)->whereNull('eliminada_en_principal_en');
    }

    public function estaEnCasa(): bool
    {
        return $this->entrada_registrada_en !== null && $this->salida_registrada_en === null;
    }

    /**
     * Año e identificador de la reserva en la principal, a partir de clave_origen (AAAA:id).
     *
     * @return array{0: int, 1: int}|null
     */
    public function claveEnPrincipal(): ?array
    {
        if ($this->clave_origen === null || ! preg_match('/^(\d{4}):(\d+)$/', $this->clave_origen, $m)) {
            return null;
        }

        return [(int) $m[1], (int) $m[2]];
    }

    public function urlEnPrincipal(): ?string
    {
        $clave = $this->claveEnPrincipal();

        return $clave && $this->principal ? $this->principal->urlReserva($clave[0], $clave[1]) : null;
    }
}
