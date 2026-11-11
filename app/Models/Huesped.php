<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrigenFila;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use App\Support\Huellas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property \Illuminate\Support\Carbon|null $fecha_nacimiento
 * @property string|null $lugar_nacimiento
 * @property string|null $documento_tipo_texto
 * @property string|null $id_fiscal_personal
 * @property string|null $id_fiscal_empresa
 * @property string|null $domicilio_calle
 * @property string|null $domicilio_numero
 * @property string|null $domicilio_ciudad
 * @property string|null $domicilio_region
 * @property string|null $domicilio_codigo_postal
 * @property string|null $domicilio_pais_texto
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CampoHuesped> $campos
 * @property string|null $documento_numero
 * @property string|null $notas
 * @property int|null $creado_por
 * @property string|null $clave_origen
 * @property Carbon|null $documento_vencimiento
 * @property string|null $apodo
 * @property string|null $tratamiento
 * @property string|null $sexo
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

    protected static function booted(): void
    {
        static::creating(function (self $huesped): void {
            if (! $huesped->sincronizada) {
                $huesped->creado_por ??= auth()->id();
            }
        });

        // El número va cifrado; su HMAC permite buscarlo por coincidencia exacta (D-022).
        static::saving(function (self $huesped): void {
            if ($huesped->isDirty('documento_numero')) {
                $numero = $huesped->documento_numero;
                $huesped->setAttribute('documento_numero_hmac', blank($numero) ? null : Huellas::hmacDocumento((string) $numero));
            }
        });
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

    /** @return BelongsToMany<Reserva, $this> */
    public function reservasComoAcompanante(): BelongsToMany
    {
        return $this->belongsToMany(Reserva::class, 'reservas_huespedes');
    }

    /** @return HasMany<SolicitudPrivacidad, $this> */
    public function solicitudesPrivacidad(): HasMany
    {
        return $this->hasMany(SolicitudPrivacidad::class);
    }

    /**
     * Quien busca escribe el nombre completo, y va repartido en tres columnas: cada palabra basta con que esté en una.
     * Las tablas de Filament no lo necesitan: ya separan la búsqueda por palabras.
     *
     * @param  Builder<Huesped>  $consulta
     */
    public function scopeConNombre(Builder $consulta, string $busqueda): void
    {
        foreach (preg_split('/\s+/u', trim($busqueda), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $palabra) {
            $patron = '%'.addcslashes($palabra, '%_\\').'%';
            $consulta->where(fn (Builder $q): Builder => $q->where('nombre', 'like', $patron)->orWhere('apellido1', 'like', $patron)->orWhere('apellido2', 'like', $patron));
        }
    }

    /**
     * El número con que se le conoce: el de la principal si viene de allá, para reconocerlo en los dos lados.
     */
    public function codigo(): string
    {
        return $this->sincronizada && filled($this->clave_origen) ? (string) $this->clave_origen : (string) $this->id;
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
