<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace App\Enums;

/**
 * Tentativo: sus reservas están sin confirmar. Cancelar el grupo cancela sus reservas.
 */
enum EstadoGrupo: string
{
    use ConEtiqueta;

    case Tentativo = 'tentativo';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
}
