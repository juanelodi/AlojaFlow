<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Lo que se cobra por lo que marca un medidor.
 */
enum ServicioMedido: string
{
    use ConEtiqueta;

    case Luz = 'luz';
    case Agua = 'agua';
    case Gas = 'gas';

    /**
     * En qué se mide. Es lo que marca el medidor, en cualquier idioma.
     */
    public function medida(): string
    {
        return match ($this) {
            self::Luz => 'kWh',
            self::Agua, self::Gas => 'm³',
        };
    }
}
