<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Pagos;

use RuntimeException;

/**
 * La pasarela no aceptó la operación o no respondió. El mensaje no lleva llaves ni datos de pago.
 */
class ErrorPago extends RuntimeException
{
}
