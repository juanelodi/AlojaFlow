<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

namespace App\Enums;

enum CanalEnvio: string
{
    use ConEtiqueta;

    case Correo = 'correo';
    case WhatsappEnlace = 'whatsapp_enlace';
    case Webhook = 'webhook';
    // Solo para el historial: AlojaFlow ya no manda por la API de Meta (D-171).
    case WhatsappApi = 'whatsapp_api';
}
