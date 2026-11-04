<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'EstadoReserva' => [
        'sin_confirmar' => 'À confirmer',
        'confirmada' => 'Confirmée',
        'cancelada' => 'Annulée',
    ],
    'BaseCargo' => [
        'por_estancia' => 'Par séjour',
        'por_noche' => 'Par nuit',
        'por_persona' => 'Par personne',
        'por_persona_noche' => 'Par personne et par nuit',
        'porcentaje_alojamiento' => 'Pourcentage de l’hébergement',
    ],
    'MetodoPago' => [
        'efectivo' => 'Espèces',
        'tarjeta' => 'Carte',
        'transferencia' => 'Virement',
        'otro' => 'Autre',
    ],
];
