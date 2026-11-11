<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Camping;

use RuntimeException;

/**
 * Una lectura que no se puede guardar o quitar. El mensaje ya viene traducido.
 */
final class ErrorLectura extends RuntimeException
{
}
