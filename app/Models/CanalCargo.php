<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\BaseCargoCanal;
use App\Enums\CalculoCargoCanal;
use App\Enums\ClaseCargoCanal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lo que un canal le quita a una reserva, además de su comisión: cargos por servicio o por cobrar, impuestos y retenciones (D-149).
 *
 * @property int $id
 * @property int $canal_id
 * @property string $nombre
 * @property ClaseCargoCanal $clase
 * @property CalculoCargoCanal $calculo
 * @property string $valor
 * @property BaseCargoCanal $base
 * @property bool $activo
 * @property-read Canal $canal
 */
class CanalCargo extends ModeloBase
{
    protected $table = 'canales_cargos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'clase' => ClaseCargoCanal::class,
            'calculo' => CalculoCargoCanal::class,
            'valor' => 'decimal:4',
            'base' => BaseCargoCanal::class,
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Canal, $this> */
    public function canal(): BelongsTo
    {
        return $this->belongsTo(Canal::class);
    }

    public function esRetencion(): bool
    {
        return in_array($this->clase, [ClaseCargoCanal::RetencionRenta, ClaseCargoCanal::RetencionIva], true);
    }
}
