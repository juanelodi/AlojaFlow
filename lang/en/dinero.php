<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'politicas' => [
        'no_reembolsable' => 'Non-refundable',
        'no_reembolsable_ayuda' => 'If they cancel, at any time, the full amount is charged.',
        'modelo' => 'policy',
        'modelos' => 'Policies',
        'nombre' => 'Name',
        'dias_gratis' => 'Free cancellation up to (days before)',
        'penalizacion' => 'Penalty after that (%)',
        'no_show' => 'No-show penalty (%)',
        'deposito' => 'Deposit (%)',
        'deposito_plazo' => 'Days to pay the deposit',
        'texto' => 'Text for the guest',
        'predeterminada' => 'Default',
        'predeterminada_ayuda' => 'Applied when the booking\'s channel has none of its own.',
        'vacio' => 'No policies yet.',
        'resumen_cancelacion' => 'Under the «:politica» policy, the penalty is :penalizacion. Already paid :pagado: to refund :devolver, to charge :cobrar.',
        'no_show_accion' => 'No-show',
        'no_show_ayuda' => 'Marks that the guest did not show up. In an independent property, it also cancels the booking and frees the nights.',
        'no_show_nota' => 'Note',
        'no_show_ok' => 'Marked as no-show. Penalty: :importe.',
        'error_no_show' => 'It can only be marked on the arrival day or later, and if check-in was not recorded.',
    ],
];
