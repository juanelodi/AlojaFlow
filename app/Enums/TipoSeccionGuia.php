<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
