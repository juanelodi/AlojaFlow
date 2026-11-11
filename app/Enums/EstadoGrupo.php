<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
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
