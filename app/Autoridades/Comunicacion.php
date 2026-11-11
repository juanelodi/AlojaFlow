<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Autoridades;

/**
 * Una reserva lista para comunicarse, o lo que le falta. Vive lo que dura el envío: nada de esto se guarda.
 */
final readonly class Comunicacion
{
    /**
     * @param  array<string, mixed>  $datos
     * @param  list<array{persona: string, campo: string}>  $faltan
     * @param  string  $huella  cambia si cambia algo de lo comunicado, y entonces hay que anular y mandarla de nuevo
     */
    public function __construct(public array $datos, public array $faltan, public string $huella)
    {
    }

    public function completa(): bool
    {
        return $this->faltan === [];
    }
}
