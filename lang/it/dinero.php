<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'politicas' => [
        'no_reembolsable' => 'Non rimborsabile',
        'no_reembolsable_ayuda' => 'Se cancellano, in qualsiasi momento, si addebita il totale.',
        'modelo' => 'politica',
        'modelos' => 'Politiche',
        'nombre' => 'Nome',
        'dias_gratis' => 'Cancellazione gratuita fino a (giorni prima)',
        'penalizacion' => 'Penale successiva (%)',
        'no_show' => 'Penale in caso di mancato arrivo (%)',
        'deposito' => 'Acconto (%)',
        'deposito_plazo' => 'Termine per pagare l’acconto (giorni)',
        'texto' => 'Testo per l’ospite',
        'predeterminada' => 'Predefinita',
        'predeterminada_ayuda' => 'Si applica quando il canale della prenotazione non ne ha una propria.',
        'vacio' => 'Non ci sono ancora politiche.',
        'resumen_cancelacion' => 'Secondo la politica «:politica», la penale è di :penalizacion. Ha già pagato :pagado: da rimborsare :devolver, da incassare :cobrar.',
        'no_show_accion' => 'Non arrivato',
        'no_show_ayuda' => 'Indica che l’ospite non si è presentato. In una struttura indipendente, inoltre, annulla la prenotazione e libera le notti.',
        'no_show_nota' => 'Nota',
        'no_show_ok' => 'Segnato come non arrivato. Penale: :importe.',
        'error_no_show' => 'Si può segnare solo dal giorno di arrivo in poi, e se non è stato registrato il check-in.',
    ],
];
