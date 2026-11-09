<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

/**
 * El código de acceso de una reserva o el fijo de una unidad (D-156). Va cifrado: abre una puerta.
 *
 * @property int $id
 * @property int|null $reserva_id
 * @property int|null $unidad_id
 * @property string $codigo
 * @property int|null $creado_por
 */
class CodigoAcceso extends ModeloBase
{
    protected $table = 'codigos_acceso';

    protected $guarded = ['id'];

    /** Nunca sale en un arreglo ni en un JSON. */
    protected $hidden = ['codigo'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['codigo' => 'encrypted'];
    }
}
