<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Cómo se calcula un cargo adicional o un impuesto.
 */
enum BaseCargo: string
{
    use ConEtiqueta;

    case PorEstancia = 'por_estancia';
    case PorNoche = 'por_noche';
    case PorPersona = 'por_persona';
    case PorPersonaNoche = 'por_persona_noche';
    case PorcentajeAlojamiento = 'porcentaje_alojamiento';

    /**
     * Si se cobra por cada noche: solo entonces importa cuáles cuentan.
     */
    public function esPorNoche(): bool
    {
        return in_array($this, [self::PorNoche, self::PorPersonaNoche], true);
    }
}
