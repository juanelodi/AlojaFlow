<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Dónde se puede omitir a un cliente (D-074). El valor es la columna de `omisiones`.
 */
enum LugarOmision: string
{
    case Aviso = 'en_aviso';
    case Informes = 'en_informes';
    case Mensajes = 'en_mensajes';
}
