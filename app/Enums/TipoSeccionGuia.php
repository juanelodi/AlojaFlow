<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoSeccionGuia: string
{
    use ConEtiqueta;

    case Llegada = 'llegada';
    case Wifi = 'wifi';
    case Reglas = 'reglas';
    case Equipo = 'equipo';
    case Recomendaciones = 'recomendaciones';
    case Contactos = 'contactos';
    case Salida = 'salida';
    case Otro = 'otro';
}
