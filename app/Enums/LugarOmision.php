<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Dónde se puede omitir a un cliente (D-074). El valor es la columna de `omisiones`.
 */
enum LugarOmision: string
{
    case Hoy = 'en_hoy';
    case Calendario = 'en_calendario';
    case Limpieza = 'en_limpieza';
    case Aviso = 'en_aviso';
    case Informes = 'en_informes';
    case Mensajes = 'en_mensajes';
}
