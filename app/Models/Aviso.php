<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\NivelAviso;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aviso persistente de una propiedad: sincronización fallida, principal sin inicio de sesión…
 *
 * @property int $id
 * @property int|null $propiedad_id
 * @property string $tipo
 * @property NivelAviso $nivel
 * @property array<string, mixed>|null $datos
 * @property string|null $clave_unica
 * @property Carbon $abierto_en
 * @property Carbon|null $resuelto_en
 * @property-read Propiedad|null $propiedad
 */
class Aviso extends ModeloBase
{
    /** Los que ningún proceso puede dar por resueltos: los cierra quien ya los revisó. */
    public const SE_CIERRAN_A_MANO = ['pago_no_coincide', 'pago_sin_lugar', 'pago_duplicado', 'pago_en_cancelada', 'motor_saturado', 'envios_fallidos', 'copia_no_borrada', 'huesped_pide'];

    protected $table = 'avisos';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nivel' => NivelAviso::class,
            'datos' => 'array',
            'abierto_en' => 'datetime',
            'resuelto_en' => 'datetime',
            'notificado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * Abre un aviso si no hay otro abierto con la misma clave.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function abrir(?int $propiedadId, string $tipo, NivelAviso $nivel, array $datos = []): self
    {
        $clave = $tipo.':'.($propiedadId ?? 'global');

        return self::query()->firstOrCreate(
            ['clave_unica' => $clave],
            ['propiedad_id' => $propiedadId, 'tipo' => $tipo, 'nivel' => $nivel, 'datos' => $datos, 'abierto_en' => now()],
        );
    }

    public static function resolver(?int $propiedadId, string $tipo): void
    {
        self::query()
            ->where('clave_unica', $tipo.':'.($propiedadId ?? 'global'))
            ->update(['clave_unica' => null, 'resuelto_en' => now()]);
    }

    public function mensaje(): string
    {
        return __('avisos.'.$this->tipo, array_map(fn (mixed $v): string => (string) $v, $this->datos ?? []));
    }
}
