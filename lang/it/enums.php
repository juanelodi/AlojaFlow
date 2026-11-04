<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'EstadoReserva' => [
        'sin_confirmar' => 'Da confermare',
        'confirmada' => 'Confermata',
        'cancelada' => 'Annullata',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Per soggiorno',
        'por_noche' => 'Per notte',
        'por_persona' => 'Per persona',
        'por_persona_noche' => 'Per persona e notte',
        'porcentaje_alojamiento' => 'Percentuale dell’alloggio',
    ],
    'MetodoPago' => [
        'efectivo' => 'Contanti',
        'tarjeta' => 'Carta',
        'transferencia' => 'Bonifico',
        'otro' => 'Altro',
    ],
];
