<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipoDocumentoLegal;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Aviso de privacidad, contrato, reglamento o política. Publicado ya no cambia: otro texto es otra versión.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property TipoDocumentoLegal $tipo
 * @property string $idioma
 * @property int $version
 * @property string $titulo
 * @property string $cuerpo
 * @property string|null $huella
 * @property Carbon|null $publicado_en
 * @property int|null $publicado_por
 * @property Carbon|null $retirado_en
 * @property Propiedad $propiedad
 */
class DocumentoLegal extends ModeloBase
{
    protected $table = 'documentos_legales';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tipo' => TipoDocumentoLegal::class,
            'version' => 'integer',
            'publicado_en' => 'datetime',
            'retirado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function publicado(): bool
    {
        return $this->publicado_en !== null;
    }

    public function vigente(): bool
    {
        return $this->publicado_en !== null && $this->retirado_en === null;
    }
}
