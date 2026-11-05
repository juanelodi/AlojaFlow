<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Demo\ModoDemo;
use App\Support\Ajustes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Quién cambió qué (modelo-datos.md §4.1). Nunca guarda campos cifrados ni secretos.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $propiedad_id
 * @property string $accion
 * @property string|null $modelo_tipo
 * @property int|null $modelo_id
 * @property array<string, mixed>|null $cambios
 * @property string|null $ip
 * @property Carbon $creado_en
 * @property-read User|null $usuario
 */
class RegistroAuditoria extends ModeloBase
{
    protected $table = 'registro_auditoria';

    public const UPDATED_AT = null;

    public const AJUSTE_DIAS = 'auditoria.dias';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'cambios' => 'array',
            'creado_en' => 'datetime',
        ];
    }

    public static function diasConservar(): int
    {
        $dias = Ajustes::obtener(self::AJUSTE_DIAS);

        return is_numeric($dias) ? max(30, (int) $dias) : (int) config('alojaflow.dias_conservar_auditoria');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function anotar(string $accion, ?Model $modelo = null, array $cambios = [], ?int $propiedadId = null): void
    {
        // En la demostración pública, la auditoría la ve cualquiera: no guarda de dónde entró cada quien.
        $peticion = app()->runningInConsole() || ModoDemo::activo() ? null : request();

        self::query()->create([
            'user_id' => auth()->id(),
            'propiedad_id' => $propiedadId,
            'accion' => $accion,
            'modelo_tipo' => $modelo ? class_basename($modelo) : null,
            'modelo_id' => $modelo?->getKey(),
            'cambios' => $cambios === [] ? null : $cambios,
            'ip' => $peticion?->ip(),
            'agente' => $peticion ? mb_substr((string) $peticion->userAgent(), 0, 255) : null,
        ]);
    }
}
