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
 * Texto de un mensaje, por idioma y con variables entre llaves.
 *
 * @property int $id
 * @property int $propiedad_id
 * @property string $codigo
 * @property string $nombre
 * @property array<string, string|null>|null $asunto
 * @property array<string, string|null> $cuerpo
 * @property bool $activa
 * @property Propiedad $propiedad
 */
class Plantilla extends ModeloBase
{
    protected $table = 'plantillas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'asunto' => 'array',
            'cuerpo' => 'array',
            'activa' => 'boolean',
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
    public function texto(string $campo, string $idioma): string
    {
        $textos = array_filter((array) $this->getAttribute($campo), fn (mixed $t): bool => filled($t));

        return (string) ($textos[$idioma] ?? $textos[$this->propiedad->idioma] ?? reset($textos) ?: '');
    }
}
