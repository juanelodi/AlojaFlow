<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

/**
 * Lo que se ajusta a un usuario en una propiedad, encima de su rol (D-075): permisos de más o de menos, y límites.
 *
 * @property int $id
 * @property int $user_id
 * @property int $propiedad_id
 * @property list<string>|null $permisos_agregados
 * @property list<string>|null $permisos_quitados
 * @property bool $solo_propias
 * @property bool $sin_iniciadas
 * @property int|null $horas_modificar
 * @property int|null $horas_cancelar
 */
class AccesoUsuario extends ModeloBase
{
    protected $table = 'accesos_usuario';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'permisos_agregados' => 'array',
            'permisos_quitados' => 'array',
            'solo_propias' => 'boolean',
            'sin_iniciadas' => 'boolean',
            'horas_modificar' => 'integer',
            'horas_cancelar' => 'integer',
        ];
    }
}
