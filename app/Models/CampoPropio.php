<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EntidadCampo;
use App\Enums\TipoCampoPropio;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Un dato que el alojamiento quiere guardar y el sistema no pide: la placa del coche, la habitación preferida (D-122).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property EntidadCampo $entidad
 * @property string $nombre
 * @property string $clave lo que va en los documentos: {campo_clave}
 * @property TipoCampoPropio $tipo
 * @property int $orden
 * @property bool $activo
 * @property-read Propiedad $propiedad
 */
class CampoPropio extends ModeloBase
{
    protected $table = 'campos_propios';

    protected $guarded = ['id'];

    protected $attributes = ['tipo' => 'texto', 'orden' => 0, 'activo' => true];

    protected static function booted(): void
    {
        // La clave nace del nombre y no cambia: ya puede estar escrita en un documento.
        static::creating(function (CampoPropio $campo): void {
            $base = (string) Str::of($campo->nombre)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->limit(50, '');
            $base = $base === '' ? 'campo' : $base;
            $clave = $base;
            for ($i = 2; self::query()->where('propiedad_id', $campo->propiedad_id)->where('clave', $clave)->exists(); $i++) {
                $clave = $base.'_'.$i;
            }
            $campo->clave = $clave;
        });
    }

    protected function casts(): array
    {
        return [
            'entidad' => EntidadCampo::class,
            'tipo' => TipoCampoPropio::class,
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<ValorCampoPropio, $this> */
    public function valores(): HasMany
    {
        return $this->hasMany(ValorCampoPropio::class);
    }

    /**
     * La variable que lo trae a un documento o a un mensaje.
     */
    public function variable(): string
    {
        return '{campo_'.$this->clave.'}';
    }

    /**
     * Lo guardado, como se lee.
     */
    public function legible(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        return $this->tipo === TipoCampoPropio::SiNo ? __('documentos_propios.si') : $valor;
    }
}
