<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Comprobantes;

use RuntimeException;

/**
 * Un comprobante que no se puede recibir. El mensaje ya viene traducido, para enseñárselo a quien lo subió.
 */
final class ComprobanteRechazado extends RuntimeException
{
}
