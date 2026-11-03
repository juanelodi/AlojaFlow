<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'This data comes from the main installation (HotelDruid) and can only be changed there.',
    'redireccion' => 'The main installation answered with a redirect to :destino. Check the address.',
    'version_no_admitida' => 'The main installation uses schema :version, which AlojaFlow does not support (it supports 3.08 and 3.081).',
    'copia_cortada' => 'The backup arrived truncated: its end is missing.',
    'falta_tabla' => 'The backup is missing table :tabla.',
    'destino' => [
        'url_invalida' => 'The address of the main installation is not valid.',
        'sin_barra_final' => 'The address must point to the installation folder and end with “/”.',
        'solo_https' => 'The address must start with https://.',
        'sin_resolver' => 'Server :host was not found.',
        'red_local' => 'Server :host is on a local network; this is only allowed if the installation permits it and “Allow local network” is enabled.',
    ],
    'errores' => [
        'SUB-001' => 'Could not connect to the main installation.',
        'SUB-002' => 'The HTTPS certificate of the main installation is not valid.',
        'SUB-003' => 'The main installation rejected the user or password, the user cannot create backups or has two-factor authentication enabled. Synchronisation is paused so the account does not get locked.',
        'SUB-005' => 'The main installation reached its daily backup limit. It will resume tomorrow; consider a lower frequency.',
        'SUB-006' => 'The requested year does not exist in the main installation.',
        'SUB-007' => 'The main installation cannot write the backup to its data folder.',
        'SUB-008' => 'The backup exceeds the maximum size or arrived incomplete.',
        'SUB-009' => 'The compressed backup arrived damaged.',
        'SUB-010' => 'The answer is not a valid HotelDruid backup.',
        'SUB-011' => 'The schema version of the main installation is not supported.',
        'SUB-012' => 'The backup is incomplete.',
        'SUB-013' => 'The backup could not be deleted from the main installation.',
        'SUB-014' => 'A synchronisation of this main installation is already running.',
        'SUB-015' => 'The main installation took too long to answer.',
        'SUB-016' => 'The address of the main installation is not allowed.',
        'SUB-099' => 'The main installation gave an answer AlojaFlow does not recognise.',
    ],
];
