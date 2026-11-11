<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Autoridades;

/**
 * Lo que la autoridad dice de un lote que ya recibió.
 */
final readonly class ResultadoLote
{
    /**
     * @param  bool  $terminado  ya lo procesó; si no, hay que volver a preguntar
     * @param  string|null  $errorGeneral  no se procesó ninguna
     * @param  array<int, array{codigo: string|null, error: string|null}>  $comunicaciones  por su posición en el envío, desde 1
     */
    public function __construct(public bool $terminado, public ?string $errorGeneral = null, public array $comunicaciones = [])
    {
    }
}
