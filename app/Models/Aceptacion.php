<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificacionAceptacion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Prueba de que alguien aceptó un documento. Es inmutable: el plazo solo anonimiza a quien firmó.
 *
 * @property int $id
 * @property int $documento_legal_id
 * @property int $propiedad_id
 * @property int|null $reserva_id
 * @property int|null $registro_llegada_id
 * @property string $firmante_nombre
 * @property string $huella_documento
 * @property Carbon $aceptada_en
 * @property string|null $ip
 * @property string|null $agente
 * @property VerificacionAceptacion $verificacion
 * @property int|null $codigo_otp_id
 * @property int|null $copia_archivo_id
 * @property \Illuminate\Support\Carbon|null $conservar_hasta
 * @property DocumentoLegal $documento
 * @property Archivo|null $copia
 */
class Aceptacion extends ModeloBase
{
    protected $table = 'aceptaciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'aceptada_en' => 'datetime',
            'verificacion' => VerificacionAceptacion::class,
            'conservar_hasta' => 'date',
        ];
    }

    /** @return BelongsTo<DocumentoLegal, $this> */
    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoLegal::class, 'documento_legal_id');
    }

    /** @return BelongsTo<Archivo, $this> */
    public function copia(): BelongsTo
    {
        return $this->belongsTo(Archivo::class, 'copia_archivo_id');
    }
}
