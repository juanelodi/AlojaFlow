<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos fiscales de quien recibe la factura, guardados para la próxima vez.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property int|null $huesped_id
 * @property string|null $pais
 * @property string $identificador_fiscal
 * @property string $nombre
 * @property string|null $regimen_fiscal
 * @property string|null $codigo_postal
 * @property string|null $uso
 * @property string|null $correo
 */
class ReceptorFiscal extends ModeloBase
{
    protected $table = 'receptores_fiscales';

    protected $guarded = ['id'];

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /** @return BelongsTo<Huesped, $this> */
    public function huesped(): BelongsTo
    {
        return $this->belongsTo(Huesped::class);
    }
}
