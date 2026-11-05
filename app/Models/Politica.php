<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Política de cancelación, no-show y depósito. Se aplica la del canal o la predeterminada.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property int $dias_cancelacion_gratuita
 * @property bool $no_reembolsable
 * @property string $penalizacion_porcentaje
 * @property string $no_show_porcentaje
 * @property string $deposito_porcentaje
 * @property int $deposito_plazo_dias
 * @property string|null $texto
 * @property bool $predeterminada
 */
class Politica extends ModeloBase
{
    protected $table = 'politicas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'dias_cancelacion_gratuita' => 'integer',
            'no_reembolsable' => 'boolean',
            'penalizacion_porcentaje' => 'decimal:2',
            'no_show_porcentaje' => 'decimal:2',
            'deposito_porcentaje' => 'decimal:2',
            'deposito_plazo_dias' => 'integer',
            'predeterminada' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
