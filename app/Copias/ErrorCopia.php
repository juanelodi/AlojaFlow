<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Copias;

use RuntimeException;

/**
 * Una copia de seguridad no se pudo crear o restaurar. El mensaje ya viene traducido.
 */
class ErrorCopia extends RuntimeException
{
}
