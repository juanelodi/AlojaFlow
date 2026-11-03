<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoContacto;
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
     * Enlace de WhatsApp (wa.me) si es un teléfono con prefijo de país.
     */
    public function enlaceWhatsapp(): ?string
    {
        if ($this->tipo !== TipoContacto::Telefono || ! str_starts_with($this->valor_normalizado, '+')) {
            return null;
        }

        return 'https://wa.me/'.ltrim($this->valor_normalizado, '+');
    }
}
