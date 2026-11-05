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
 * Un cliente, o todos los de un tipo, que no debe salir en ciertos lugares (D-074).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $huesped_id
 * @property string|null $campo
 * @property string|null $valor
 * @property bool $en_aviso
 * @property bool $en_informes
 * @property bool $en_mensajes
 * @property string|null $motivo
 * @property int|null $creada_por
 * @property Huesped|null $huesped
 * @property Propiedad $propiedad
 */
class Omision extends ModeloBase
{
    protected $table = 'omisiones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['en_aviso' => 'boolean', 'en_informes' => 'boolean', 'en_mensajes' => 'boolean'];
    }

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }
}
