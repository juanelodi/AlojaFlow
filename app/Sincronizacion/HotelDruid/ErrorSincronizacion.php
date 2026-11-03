<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use RuntimeException;
use Throwable;

/**
 * Fallo de un paso de la sincronización. El detalle nunca lleva credenciales.
 */
final class ErrorSincronizacion extends RuntimeException
{
    public function __construct(
        public readonly CodigoError $codigo,
        public readonly string $paso,
        string $detalle = '',
        ?Throwable $previa = null,
    ) {
        parent::__construct($detalle !== '' ? $detalle : $codigo->etiqueta(), 0, $previa);
    }
}
