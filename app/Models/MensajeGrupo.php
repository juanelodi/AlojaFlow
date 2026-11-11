<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lo que se escriben el coordinador y el alojamiento.
 *
 * @property int $id
 * @property int $grupo_id
 * @property bool $del_coordinador
 * @property string $texto
 * @property int|null $user_id
 * @property Carbon|null $leido_en
 * @property Carbon|null $creado_en
 * @property-read Grupo $grupo
 * @property-read User|null $usuario
 */
class MensajeGrupo extends ModeloBase
{
    protected $table = 'mensajes_grupo';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['del_coordinador' => 'boolean', 'leido_en' => 'datetime'];
    }

    /** @return BelongsTo<Grupo, $this> */
    public function grupo(): BelongsTo
    {
        return $this->belongsTo(Grupo::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
