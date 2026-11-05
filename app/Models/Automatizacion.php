<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccionAutomatizacion;
use App\Enums\DisparadorAutomatizacion;
use App\Enums\EstadoRegistroLlegada;
use App\Enums\EstadoReserva;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Qué mensaje se manda, cuándo y a quién.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property DisparadorAutomatizacion $disparador
 * @property int $dias
 * @property string|null $hora
 * @property array<string, mixed>|null $condiciones
 * @property AccionAutomatizacion $accion
 * @property int|null $plantilla_id
 * @property string|null $destino
 * @property bool $activa
 * @property \Illuminate\Support\Carbon|null $creado_en
 * @property Plantilla|null $plantilla
 * @property Propiedad $propiedad
 */
class Automatizacion extends ModeloBase
{
    protected $table = 'automatizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'disparador' => DisparadorAutomatizacion::class,
            'accion' => AccionAutomatizacion::class,
            'dias' => 'integer',
            'condiciones' => 'array',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Plantilla, $this> */
    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    /**
     * Antes de la llegada, si no se cumple todavía, se vuelve a revisar cada día: los datos de acceso esperan a que haya pagado.
     */
    public function esperaACumplir(): bool
    {
        return $this->disparador === DisparadorAutomatizacion::AntesLlegada && ! empty(((array) $this->condiciones)['esperar']);
    }

    public function cumpleCondiciones(Reserva $reserva): bool
    {
        $condiciones = (array) $this->condiciones;
        $estado = EstadoReserva::tryFrom((string) ($condiciones['estado'] ?? ''));
        if ($estado !== null && $reserva->estado !== $estado) {
            return false;
        }
        $saldo = (string) ($condiciones['saldo'] ?? '');
        if (($saldo === 'con_saldo' && $reserva->pendiente <= 0) || ($saldo === 'sin_saldo' && $reserva->pendiente > 0)) {
            return false;
        }
        $canal = trim((string) ($condiciones['canal'] ?? ''));
        if ($canal !== '' && mb_strtolower(trim((string) $reserva->origen_texto)) !== mb_strtolower($canal)) {
            return false;
        }
        if (! empty($condiciones['llegada_hecha']) && ! RegistroLlegada::query()->where('reserva_id', $reserva->id)->whereIn('estado', [EstadoRegistroLlegada::Recibido->value, EstadoRegistroLlegada::Revisado->value])->exists()) {
            return false;
        }

        return empty($condiciones['contrato_aceptado']) || Aceptacion::query()->where('reserva_id', $reserva->id)->exists();
    }
}
