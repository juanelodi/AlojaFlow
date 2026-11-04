<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'EstadoReserva' => [
        'sin_confirmar' => 'Por confirmar',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Por estadia',
        'por_noche' => 'Por noite',
        'por_persona' => 'Por pessoa',
        'por_persona_noche' => 'Por pessoa e noite',
        'porcentaje_alojamiento' => 'Percentagem do alojamento',
    ],
    'MetodoPago' => [
        'efectivo' => 'Dinheiro',
        'tarjeta' => 'Cartão',
        'transferencia' => 'Transferência',
        'otro' => 'Outro',
    ],
];
