<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué es un cargo de un canal (D-149). Las retenciones se separan porque no son un gasto: son impuesto que el canal adelanta por el alojamiento.
 */
enum ClaseCargoCanal: string
{
    use ConEtiqueta;

    case Servicio = 'servicio';
    case ProcesamientoPago = 'procesamiento_pago';
    case Promocion = 'promocion';
    case ImpuestoSobreCargos = 'impuesto_sobre_cargos';
    case RetencionRenta = 'retencion_renta';
    case RetencionIva = 'retencion_iva';
    case Otro = 'otro';
}
