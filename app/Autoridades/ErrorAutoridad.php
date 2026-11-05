<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Autoridades;

use RuntimeException;

/**
 * La autoridad no contestó o no aceptó la petición. Su mensaje se le enseña a quien administra: nunca lleva la contraseña.
 */
final class ErrorAutoridad extends RuntimeException
{
}
