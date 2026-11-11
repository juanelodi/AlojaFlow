<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoContacto;
use App\Support\Contactos;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $huesped_id
 * @property TipoContacto $tipo
 * @property string $valor
 * @property string $valor_normalizado
 * @property int $orden
 */
class ContactoHuesped extends ModeloBase
{
    protected $table = 'contactos_huesped';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoContacto::class,
        ];
    }

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }

    /**
     * Enlace de WhatsApp (wa.me). Sin prefijo escrito, usa el del país de la propiedad.
     */
    public function enlaceWhatsapp(?string $pais = null): ?string
    {
        $numero = $this->tipo === TipoContacto::Telefono ? Contactos::internacional($this->valor_normalizado, $pais) : null;

        return $numero === null ? null : 'https://wa.me/'.ltrim($numero, '+');
    }
}
