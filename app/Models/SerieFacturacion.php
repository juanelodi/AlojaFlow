<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumentoFiscal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una serie con su consecutivo. El número se toma bloqueando la fila: sin huecos ni repetidos.
 *
 * @property int $id
 * @property int $entidad_fiscal_id
 * @property string $serie
 * @property TipoDocumentoFiscal $tipo_documento
 * @property int $siguiente_numero
 * @property bool $activa
 * @property EntidadFiscal $entidad
 */
class SerieFacturacion extends ModeloBase
{
    protected $table = 'series_facturacion';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumentoFiscal::class,
            'siguiente_numero' => 'integer',
            'activa' => 'boolean',
        ];
    }

    /** @return BelongsTo<EntidadFiscal, $this> */
    public function entidad(): BelongsTo
    {
        return $this->belongsTo(EntidadFiscal::class, 'entidad_fiscal_id');
    }
}
