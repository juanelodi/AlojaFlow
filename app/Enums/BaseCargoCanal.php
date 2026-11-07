<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Sobre qué se calcula un porcentaje de un canal (D-149). «Tarifa base» es el alojamiento, sin cargos extra ni impuestos; «final», lo que paga el huésped.
 */
enum BaseCargoCanal: string
{
    use ConEtiqueta;

    case TarifaBase = 'tarifa_base';
    case SinImpuestos = 'sin_impuestos';
    case TotalFinal = 'total_final';
    case Comisiones = 'comisiones';
}
