<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrigenFila;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ficha de huésped de una propiedad (proyección; modelo-datos.md §4.5, D-020).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string|null $nombre
 * @property string|null $apellido1
 * @property string|null $apellido2
 * @property string|null $idioma
 * @property string|null $nacionalidad_texto
 * @property string|null $documento_numero
 * @property string|null $notas
 * @property Carbon|null $eliminado_en_principal_en
 * @property OrigenFila $origen
 * @property bool $sincronizada
 * @property-read Propiedad $propiedad
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ContactoHuesped> $contactos
 */
class Huesped extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'huespedes';

    protected $guarded = ['id'];

    protected $hidden = ['documento_numero', 'documento_numero_hmac'];

    protected function casts(): array
    {
        return [
            'documento_numero' => 'encrypted',
            'fecha_nacimiento' => 'date',
            'documento_vencimiento' => 'date',
            'creado_en_origen' => 'datetime',
            'eliminado_en_principal_en' => 'datetime',
            'origen' => OrigenFila::class,
            'sincronizada' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<ContactoHuesped, $this> */
    public function contactos(): HasMany
    {
        return $this->hasMany(ContactoHuesped::class)->orderBy('orden');
    }

    /** @return HasMany<CampoHuesped, $this> */
    public function campos(): HasMany
    {
        return $this->hasMany(CampoHuesped::class);
    }

    /** @return HasMany<Reserva, $this> */
    public function reservasComoTitular(): HasMany
    {
        return $this->hasMany(Reserva::class, 'titular_id');
    }

    public function nombreCompleto(): string
    {
        $partes = array_filter([$this->nombre, $this->apellido1, $this->apellido2], fn (?string $p): bool => $p !== null && $p !== '');

        return $partes === [] ? __('huespedes.sin_nombre') : implode(' ', $partes);
    }

    public function telefonoPrincipal(): ?string
    {
        return $this->contactos->firstWhere('tipo', 'telefono')?->valor;
    }
}
