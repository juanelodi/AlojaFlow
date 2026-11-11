<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum TipoDocumentoLegal: string
{
    use ConEtiqueta;

    case AvisoPrivacidad = 'aviso_privacidad';
    case Contrato = 'contrato';
    case Reglamento = 'reglamento';
    case PoliticaCancelacion = 'politica_cancelacion';
    case ContratoGrupo = 'contrato_grupo';
}
