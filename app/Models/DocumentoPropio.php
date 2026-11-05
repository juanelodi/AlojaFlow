<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlcanceDocumento;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un documento que escribe el alojamiento, por idioma y con variables entre llaves (D-123).
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $nombre
 * @property AlcanceDocumento $alcance
 * @property array<string, string|null> $cuerpo
 * @property int $orden
 * @property bool $activo
 * @property-read Propiedad $propiedad
 */
class DocumentoPropio extends ModeloBase
{
    protected $table = 'documentos_propios';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'alcance' => AlcanceDocumento::class,
            'cuerpo' => 'array',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Propiedad, $this> */
    public function propiedad(): BelongsTo
    {
        return $this->belongsTo(Propiedad::class);
    }

    /**
     * El texto en ese idioma o, si falta, en el de la propiedad o en el primero que haya.
     */
    public function texto(string $idioma): string
    {
        // El editor deja un párrafo vacío en los idiomas que nadie escribió.
        $textos = array_filter((array) $this->cuerpo, fn (mixed $t): bool => trim(strip_tags((string) $t)) !== '');

        return (string) ($textos[$idioma] ?? $textos[$this->propiedad->idioma] ?? reset($textos) ?: '');
    }
}
