<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
