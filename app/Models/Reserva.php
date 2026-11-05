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
use App\Enums\TipoPrincipal;
use App\Enums\TipoReserva;
use App\Models\Concerns\ProtegidaPorSincronizacion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 * @property int|null $creada_por
 * @property int|null $empresa_id
 * @property int|null $ninos
 * @property int $version
 * @property int|null $grupo_id
 * @property int|null $cupon_id
 * @property Carbon|null $expira_en
 * @property string|null $idioma
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
 * @property-read Empresa|null $empresa
 */
class Reserva extends ModeloBase
{
    use ProtegidaPorSincronizacion;

    protected $table = 'reservas';

    protected $guarded = ['id', 'noches', 'pendiente'];

    protected static function booted(): void
    {
        // Lo necesita el límite «solo sus propias reservas» (D-075). Las de la principal o del motor no son de nadie.
        static::creating(function (self $reserva): void {
            if (! $reserva->sincronizada) {
                $reserva->creada_por ??= auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'equipo' => 'encrypted:array',
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
            'version' => 'integer',
            'expira_en' => 'datetime',
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

    /** @return HasOne<GrupoHabitacion, $this> */
    public function habitacionGrupo(): HasOne
    {
        return $this->hasOne(GrupoHabitacion::class);
    }

    /** @return HasMany<CuotaReserva, $this> */
    public function cuotas(): HasMany
    {
        return $this->hasMany(CuotaReserva::class)->orderBy('vence_en')->orderBy('id');
    }

    /** @return HasMany<ComprobantePago, $this> */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(ComprobantePago::class);
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<Cupon, $this> */
    public function cupon(): BelongsTo
    {
        return $this->belongsTo(Cupon::class);
    }

    /** @return HasMany<CargoReserva, $this> */
    public function cargos(): HasMany
    {
        return $this->hasMany(CargoReserva::class);
    }

    /** @return HasMany<EnlacePago, $this> */
    public function enlacesPago(): HasMany
    {
        return $this->hasMany(EnlacePago::class);
    }

    /** @return HasMany<ImpuestoReserva, $this> */
    public function impuestos(): HasMany
    {
        return $this->hasMany(ImpuestoReserva::class);
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<NotaReserva, $this> */
    public function notas(): HasMany
    {
        return $this->hasMany(NotaReserva::class);
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
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

    /**
     * La gestiona AlojaFlow, no una principal.
     */
    public function esPropia(): bool
    {
        return ! $this->sincronizada && $this->propiedad->esIndependiente();
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
        // Los bloqueos de otra instalación llegan como «b:id» y allá no son una reserva.
        if ($this->principal?->tipo === TipoPrincipal::AlojaFlow) {
            return ctype_digit((string) $this->clave_origen) ? $this->principal->urlReservaEnAlojaFlow((int) $this->clave_origen) : null;
        }
        $clave = $this->claveEnPrincipal();

        return $clave && $this->principal ? $this->principal->urlReserva($clave[0], $clave[1]) : null;
    }
}
