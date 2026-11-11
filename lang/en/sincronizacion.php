<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'This data comes from the main installation and can only be changed there.',
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
    'respondio' => 'It happened :paso, and the answer was: “:detalle”.',
    'pasos' => [
        'probar' => 'while testing the connection',
        'validar' => 'while checking the address',
        'crear' => 'while requesting the backup',
        'descargar' => 'while downloading the backup',
        'borrar' => 'while deleting the backup',
        'descomprimir' => 'while decompressing the backup',
        'leer' => 'while reading the backup',
        'interno' => 'while processing the backup',
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
        'SUB-017' => 'The other installation did not respond as expected. Check that it has AlojaFlow 1.0.12 or later and the subordination module turned on.',
        'SUB-018' => 'The other installation rejected the connection code. It may have been revoked: ask for a new one.',
        'SUB-098' => 'AlojaFlow could not process what it received from the main installation. The fault is in AlojaFlow, not in the main installation: it has been written to the log.',
        'SUB-099' => 'The main installation gave an answer AlojaFlow does not recognise.',
    ],

    'compartir' => [
        'titulo' => 'Share with another installation',
        'ayuda' => 'Another AlojaFlow installation can copy this property, for reading only: bookings are still changed here. Photos and files never travel.',
        'sin_conexiones' => 'You are not sharing it with anyone yet',
        'nombre' => 'Name',
        'nombre_ayuda' => 'To recognise it, for example «Head office».',
        'documentos' => 'Include identity documents',
        'documentos_ayuda' => 'When off, neither the number nor the other document details travel. Turn it on only if the other installation needs them.',
        'ultimo_uso' => 'Last used',
        'estado' => 'Status',
        'activa' => 'Active',
        'revocada' => 'Revoked',
        'crear' => 'Share',
        'revocar' => 'Revoke',
        'revocar_texto' => 'The other installation will no longer be able to copy. What it already copied stays there.',
        'codigo_titulo' => 'Connection code',
        'codigo_texto' => 'Copy it and paste it in the other installation when creating the subordinate property. For safety, it is not shown again.',
        'codigo_listo' => 'I copied it',
    ],

    'formulario' => [
        'tipo' => 'Which system it copies from',
        'codigo' => 'Connection code',
        'codigo_ayuda' => 'It is generated in the other installation: when editing the property, under «Share with another installation». If it is already connected, leave it empty to keep the current one.',
        'codigo_invalido' => 'That is not an AlojaFlow connection code. Copy it in full.',
    ],
];
