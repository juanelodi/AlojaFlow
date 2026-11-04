<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Propiedades;

use RuntimeException;

/**
 * No se pudo pasar la propiedad a independiente. El mensaje es para la persona que lo intentó.
 */
class ErrorIndependizar extends RuntimeException
{
}
