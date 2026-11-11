<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

use App\Seguridad\Acceso;

enum ClaseArchivo: string
{
    use ConEtiqueta;

    case DocumentoIdentidad = 'documento_identidad';
    case Firma = 'firma';
    case CopiaAceptacion = 'copia_aceptacion';
    case FotoTarea = 'foto_tarea';
    case Plano = 'plano';
    case Exportacion = 'exportacion';
    case Comprobante = 'comprobante';
    case FotoPublica = 'foto_publica';
    case ComprobanteGasto = 'comprobante_gasto';

    /**
     * El permiso que hace falta para verlo.
     */
    public function permiso(): string
    {
        return match ($this) {
            self::FotoTarea => Acceso::LIMPIEZA,
            self::Plano => Acceso::OPERACION,
            self::Comprobante, self::ComprobanteGasto => Acceso::RESERVAS,
            self::FotoPublica => Acceso::CATALOGO,
            self::DocumentoIdentidad, self::Firma, self::CopiaAceptacion, self::Exportacion => Acceso::HUESPEDES,
        };
    }
}
