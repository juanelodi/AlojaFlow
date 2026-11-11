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
 * Un dispositivo donde el usuario pidió mantener la sesión (D-076). Se guarda solo la huella de lo que lleva la cookie.
 *
 * @property int $id
 * @property int $user_id
 * @property string $selector
 * @property string $validador_hash
 * @property string|null $validador_anterior_hash
 * @property Carbon|null $rotado_en
 * @property string $huella_contrasena
 * @property string|null $agente
 * @property string|null $ip
 * @property Carbon|null $ultimo_uso_en
 * @property Carbon $expira_en
 * @property-read User $usuario
 */
class DispositivoRecordado extends ModeloBase
{
    public const UPDATED_AT = null;

    protected $table = 'dispositivos_recordados';

    protected $guarded = ['id'];

    protected $hidden = ['validador_hash', 'validador_anterior_hash', 'huella_contrasena'];

    protected function casts(): array
    {
        return ['rotado_en' => 'datetime', 'ultimo_uso_en' => 'datetime', 'expira_en' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
