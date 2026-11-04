<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'EstadoReserva' => [
        'sin_confirmar' => 'Unbestätigt',
        'confirmada' => 'Bestätigt',
        'cancelada' => 'Storniert',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Pro Aufenthalt',
        'por_noche' => 'Pro Nacht',
        'por_persona' => 'Pro Person',
        'por_persona_noche' => 'Pro Person und Nacht',
        'porcentaje_alojamiento' => 'Prozent der Unterkunft',
    ],
    'MetodoPago' => [
        'efectivo' => 'Bar',
        'tarjeta' => 'Karte',
        'transferencia' => 'Überweisung',
        'otro' => 'Sonstiges',
    ],
];
