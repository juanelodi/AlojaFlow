<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoPrincipal;
use App\Enums\MetodoPeticion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Instalación de HotelDruid o AlojaFlow 1.2.x de la que se lee la copia (subordinacion.md A2).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $url_base
 * @property string|null $usuario
 * @property string|null $contrasena
 * @property MetodoPeticion $metodo
 * @property bool $compresion
 * @property int $frecuencia_minutos
 * @property bool $permitir_red_local
 * @property int|null $anio_consulta
 * @property EstadoPrincipal $estado
 * @property string|null $codigo_pausa
 * @property Carbon|null $pausada_hasta
 * @property Carbon|null $proxima_en
 * @property int $fallos_consecutivos
 * @property int|null $ejecucion_vigente_id
 * @property string|null $version_esquema
 * @property string|null $idioma_principal
 * @property bool|null $inicio_sesion_activado
 * @property Carbon|null $fecha_copia
 * @property Carbon|null $ultima_correcta_en
 * @property-read Propiedad $propiedad
 * @property-read EjecucionSincronizacion|null $ejecucionVigente
 */
class Principal extends ModeloBase
{
    protected $table = 'principales';

    protected $fillable = [
        'url_base', 'usuario', 'contrasena', 'metodo', 'compresion', 'frecuencia_minutos',
        'permitir_red_local',
    ];

    // Las credenciales nunca salen en arreglos, JSON ni registros (regla 13).
    protected $hidden = ['usuario', 'contrasena'];

    protected function casts(): array
    {
        return [
            'usuario' => 'encrypted',
            'contrasena' => 'encrypted',
            'metodo' => MetodoPeticion::class,
            'compresion' => 'boolean',
            'permitir_red_local' => 'boolean',
            'estado' => EstadoPrincipal::class,
            'pausada_hasta' => 'datetime',
            'proxima_en' => 'datetime',
            'inicio_sesion_activado' => 'boolean',
            'fecha_copia' => 'datetime',
            'ultima_correcta_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return HasMany<EjecucionSincronizacion, $this> */
    public function ejecuciones(): HasMany
    {
        return $this->hasMany(EjecucionSincronizacion::class);
    }

    /** @return BelongsTo<EjecucionSincronizacion, $this> */
    public function ejecucionVigente(): BelongsTo
    {
        return $this->belongsTo(EjecucionSincronizacion::class, 'ejecucion_vigente_id');
    }

    public function estaPausada(): bool
    {
        if ($this->estado !== EstadoPrincipal::Pausada) {
            return false;
        }

        return $this->pausada_hasta === null || $this->pausada_hasta->isFuture();
    }

    public function toca(Carbon $ahora): bool
    {
        return ! $this->estaPausada() && ($this->proxima_en === null || $this->proxima_en->lessThanOrEqualTo($ahora));
    }

    /**
     * URL de una reserva en la principal, para «Abrir en la principal».
     */
    public function urlReserva(int $anio, int $idPrenota): string
    {
        return $this->url_base.'modifica_prenota.php?'.http_build_query(['id_prenota' => $idPrenota, 'anno' => $anio]);
    }
}
