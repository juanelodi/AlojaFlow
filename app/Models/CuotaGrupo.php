<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un pago previsto del grupo. Si está pagada o no se calcula: lo pagado se aplica a las primeras en vencer (`CalendarioPagos`).
 *
 * @property int $id
 * @property int $grupo_id
 * @property string $concepto
 * @property int $importe
 * @property CarbonImmutable $vence_en
 * @property-read Grupo $grupo
 */
class CuotaGrupo extends ModeloBase
{
    protected $table = 'cuotas_grupo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['importe' => 'integer', 'vence_en' => 'immutable_date'];
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }
}
