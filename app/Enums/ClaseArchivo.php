<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
    case FacturaXml = 'factura_xml';
    case FacturaPdf = 'factura_pdf';
    case Exportacion = 'exportacion';
    case Comprobante = 'comprobante';
    case FotoPublica = 'foto_publica';

    /**
     * El permiso que hace falta para verlo.
     */
    public function permiso(): string
    {
        return match ($this) {
            self::FotoTarea => Acceso::LIMPIEZA,
            self::Plano => Acceso::OPERACION,
            self::Comprobante => Acceso::RESERVAS,
            self::FotoPublica => Acceso::CATALOGO,
            self::FacturaXml, self::FacturaPdf => Acceso::FACTURACION,
            self::DocumentoIdentidad, self::Firma, self::CopiaAceptacion, self::Exportacion => Acceso::HUESPEDES,
        };
    }
}
