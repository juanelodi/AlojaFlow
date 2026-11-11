<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\EstadoActualizacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una actualización aplicada (o intentada) desde el panel.
 *
 * @property int $id
 * @property string $version_anterior
 * @property string $version_nueva
 * @property EstadoActualizacion $estado
 * @property bool $reinstalar
 * @property string $paquete
 * @property string $paquete_sha256
 * @property array<string, mixed>|null $resumen
 * @property string|null $detalle
 * @property int|null $user_id
 * @property Carbon|null $iniciada_en
 * @property Carbon|null $terminada_en
 * @property Carbon|null $creado_en
 * @property Carbon|null $actualizado_en
 * @property-read User|null $usuario
 */
class Actualizacion extends ModeloBase
{
    protected $table = 'actualizaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'estado' => EstadoActualizacion::class,
            'reinstalar' => 'boolean',
            'resumen' => 'array',
            'iniciada_en' => 'datetime',
            'terminada_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
