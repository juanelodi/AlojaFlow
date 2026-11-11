<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

/**
 * Un ajuste de la instalación (modelo-datos.md §4.1). Se usa a través de App\Support\Ajustes.
 *
 * @property int $id
 * @property string $clave
 * @property mixed $valor
 */
class Ajuste extends ModeloBase
{
    protected $table = 'ajustes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['valor' => 'array'];
    }
}
