<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Sincronizacion\HotelDruid;

use RuntimeException;

/**
 * Una sincronización que no se pudo completar: qué pasó (el código) y en qué paso. El mensaje es el detalle y, si no
 * lo hay, el texto del código. Nunca lleva la excepción del cliente HTTP: su mensaje puede traer la dirección con la
 * contraseña (regla 13).
 */
final class ErrorSincronizacion extends RuntimeException
{
    public function __construct(
        public readonly CodigoError $codigo,
        public readonly string $paso,
        ?string $detalle = null,
    ) {
        parent::__construct($detalle === null || $detalle === '' ? $codigo->etiqueta() : $detalle);
    }
}
