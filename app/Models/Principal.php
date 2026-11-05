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
use App\Enums\TipoPrincipal;
use App\Sincronizacion\HotelDruid\CodigoError;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Instalación de la que se copia una propiedad: HotelDruid o AlojaFlow 1.2.x (subordinacion.md A2), u otra instalación de
 * AlojaFlow (D-073). En esta última, el token de la conexión va en `contrasena`.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property TipoPrincipal $tipo
 * @property string $url_base
 * @property string|null $usuario
 * @property string|null $contrasena
 * @property MetodoPeticion $metodo
 * @property bool $compresion
 * @property int $frecuencia_minutos
 * @property int|null $dias_semana
 * @property string|null $franja_desde
 * @property string|null $franja_hasta
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
 * @property string|null $revision_origen
 * @property Carbon|null $ultima_bajada_en
 * @property-read Propiedad $propiedad
 * @property-read EjecucionSincronizacion|null $ejecucionVigente
 */
class Principal extends ModeloBase
{
    protected $table = 'principales';

    protected $fillable = [
        'tipo', 'url_base', 'usuario', 'contrasena', 'metodo', 'compresion', 'frecuencia_minutos',
        'permitir_red_local', 'dias_semana', 'franja_desde', 'franja_hasta',
    ];

    // Las credenciales nunca salen en arreglos, JSON ni registros (regla 13).
    protected $hidden = ['usuario', 'contrasena'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoPrincipal::class,
            'usuario' => 'encrypted',
            'contrasena' => 'encrypted',
            'metodo' => MetodoPeticion::class,
            'compresion' => 'boolean',
            'permitir_red_local' => 'boolean',
            'dias_semana' => 'integer',
            'estado' => EstadoPrincipal::class,
            'pausada_hasta' => 'datetime',
            'proxima_en' => 'datetime',
            'inicio_sesion_activado' => 'boolean',
            'fecha_copia' => 'datetime',
            'ultima_correcta_en' => 'datetime',
            'ultima_bajada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Con credenciales o dirección nuevas, una principal pausada por ellas vuelve a intentarlo (A5).
        static::saving(function (Principal $principal): void {
            $reanudables = [CodigoError::Credenciales->value, CodigoError::ConexionRechazada->value, CodigoError::DestinoNoPermitido->value, CodigoError::AnioInexistente->value];
            if ($principal->exists && $principal->estado === EstadoPrincipal::Pausada
                && in_array($principal->codigo_pausa, $reanudables, true)
                && $principal->isDirty(['url_base', 'usuario', 'contrasena', 'metodo', 'permitir_red_local'])) {
                $principal->forceFill([
                    'estado' => EstadoPrincipal::Activa, 'codigo_pausa' => null, 'pausada_hasta' => null,
                    'fallos_consecutivos' => 0, 'proxima_en' => now()->addSeconds(10),
                ]);
            }
        });
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function reanudar(): void
    {
        $this->forceFill([
            'estado' => EstadoPrincipal::Activa, 'codigo_pausa' => null, 'pausada_hasta' => null,
            'fallos_consecutivos' => 0, 'proxima_en' => now(),
        ])->save();
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
        return ! $this->estaPausada() && ($this->proxima_en === null || $this->proxima_en->lessThanOrEqualTo($ahora)) && $this->dentroDelHorario($ahora);
    }

    /**
     * Días y franja en la hora de la propiedad (D-093). Una franja que cruza la medianoche es del día en que empieza: la de 22:00 a 06:00 del lunes
     * sigue el martes hasta las 6. Sincronizar a mano no pasa por aquí.
     */
    public function dentroDelHorario(Carbon $ahora): bool
    {
        $local = $ahora->copy()->setTimezone($this->propiedad->zona_horaria);
        $dia = $local;
        if ($this->franja_desde !== null && $this->franja_hasta !== null) {
            $hora = $local->format('H:i');
            $desde = substr($this->franja_desde, 0, 5);
            $hasta = substr($this->franja_hasta, 0, 5);
            if ($desde < $hasta && ($hora < $desde || $hora >= $hasta)) {
                return false;
            }
            if ($desde > $hasta) {
                if ($hora < $desde && $hora >= $hasta) {
                    return false;
                }
                $dia = $hora < $hasta ? $local->copy()->subDay() : $local;
            }
        }

        return $this->dias_semana === null || ($this->dias_semana & (1 << ($dia->dayOfWeekIso - 1))) !== 0;
    }

    /**
     * URL de una reserva en la principal, para «Abrir en la principal».
     */
    public function urlReserva(int $anio, int $idPrenota): string
    {
        return $this->url_base.'modifica_prenota.php?'.http_build_query(['id_prenota' => $idPrenota, 'anno' => $anio]);
    }

    /**
     * Lo mismo para una reserva de otra instalación de AlojaFlow.
     */
    public function urlReservaEnAlojaFlow(int $id): string
    {
        return $this->url_base.'reservas/'.$id;
    }
}
