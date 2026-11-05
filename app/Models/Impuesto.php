<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Impuesto de una propiedad independiente: incluido en el precio o que se suma (modelo-datos.md §4.10).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string|null $codigo
 * @property string $nombre
 * @property BaseCargo $base
 * @property string|null $tasa
 * @property int|null $importe
 * @property bool $incluido_en_precio
 * @property CarbonImmutable|null $vigente_desde
 * @property CarbonImmutable|null $vigente_hasta
 * @property int|null $tope_noches
 * @property int|null $edad_minima
 * @property bool $fijar_al_reservar
 * @property string|null $temporada_baja_desde
 * @property string|null $temporada_baja_hasta
 * @property string|null $temporada_baja_descuento
 * @property int|null $dias_largos_desde
 * @property string|null $dias_largos_descuento
 * @property int $orden
 * @property bool $activo
 */
class Impuesto extends ModeloBase
{
    protected $table = 'impuestos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'base' => BaseCargo::class,
            'tasa' => 'decimal:4',
            'importe' => 'integer',
            'incluido_en_precio' => 'boolean',
            'vigente_desde' => 'immutable_date',
            'vigente_hasta' => 'immutable_date',
            'tope_noches' => 'integer',
            'edad_minima' => 'integer',
            'fijar_al_reservar' => 'boolean',
            'temporada_baja_descuento' => 'decimal:2',
            'dias_largos_desde' => 'integer',
            'dias_largos_descuento' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * Los que aplican a una fecha de llegada. Una tarifa que se fija al reservar se mira con el día en que se reservó (D-101).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAplicables(Builder $query, int $propiedadId, string $fecha, ?string $reservadaEn = null): Builder
    {
        $vigente = fn (Builder $q, string $dia): Builder => $q
            ->where(fn (Builder $r): Builder => $r->whereNull('vigente_desde')->orWhere('vigente_desde', '<=', $dia))
            ->where(fn (Builder $r): Builder => $r->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', $dia));

        return $query->where('propiedad_id', $propiedadId)->where('activo', true)->where(fn (Builder $q): Builder => $q
            ->where(fn (Builder $r): Builder => $vigente($r->where('fijar_al_reservar', false), $fecha))
            ->orWhere(fn (Builder $r): Builder => $vigente($r->where('fijar_al_reservar', true), $reservadaEn ?? $fecha)));
    }

    /**
     * Si esa noche cae en la temporada baja, que puede cruzar el fin de año.
     */
    public function enTemporadaBaja(CarbonImmutable $dia): bool
    {
        if ($this->temporada_baja_desde === null || $this->temporada_baja_hasta === null) {
            return false;
        }
        $hoy = $dia->format('m-d');

        return $this->temporada_baja_desde <= $this->temporada_baja_hasta
            ? $hoy >= $this->temporada_baja_desde && $hoy <= $this->temporada_baja_hasta
            : $hoy >= $this->temporada_baja_desde || $hoy <= $this->temporada_baja_hasta;
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
