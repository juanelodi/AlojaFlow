<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'See the operations screens (Today, calendar, housekeeping)',
        ],
        'datos' => [
            'ver' => 'See bookings, guests and other data',
        ],
        'indicadores' => [
            'ver' => 'See indicators and reports',
        ],
        'sincronizacion' => [
            'gestionar' => 'Manage synchronization with the main installation',
        ],
        'reservas' => [
            'gestionar' => 'Create and change bookings, guests and payments',
        ],
        'catalogo' => [
            'gestionar' => 'Create and change units and rates',
        ],
        'limpieza' => [
            'gestionar' => 'Manage housekeeping and maintenance',
        ],
        'mensajes' => [
            'gestionar' => 'Send messages to guests',
        ],
        'ajustes' => [
            'gestionar' => 'Templates, automations, calendars and booking engine',
        ],
        'huespedes' => [
            'gestionar' => 'Guest registration, documents and privacy',
        ],
        'facturacion' => [
            'gestionar' => 'Tax details, invoices and exports',
        ],
    ],
    'roles' => [
        'modelo' => 'role',
        'modelos' => 'Roles',
        'nombre' => 'Name',
        'permisos' => 'Permissions',
        'en_uso' => 'Users with this role',
        'ayuda' => 'What you change here applies to everyone with this role as soon as you save it.',
    ],
    'usuario' => [
        'ajustes' => 'Settings for this person',
        'ajustes_ayuda' => 'Optional. Only for someone who needs something different from their role.',
        'agregados' => 'In addition to their role, can',
        'quitados' => 'Even if their role allows it, cannot',
        'solo_propias' => 'Only their own bookings and guests',
        'solo_propias_ayuda' => 'Today and the lists show only what they created. The calendar shows what is occupied, without names.',
        'sin_iniciadas' => 'Do not change bookings already started',
        'sin_iniciadas_ayuda' => 'Those that have arrived or arrive today. Recording check-in, check-out and payments is still allowed.',
        'horas_modificar' => 'Hours to change a booking',
        'horas_cancelar' => 'Hours to cancel a booking',
        'horas_ayuda' => 'Counted from when it was created. Empty: no limit.',
    ],
    'limites' => [
        'no_modificar' => 'Your permissions do not allow changing this booking.',
        'ocupada' => 'Occupied',
    ],
    'sesiones' => [
        'titulo' => 'Sessions and devices',
        'abiertas' => 'Open sessions',
        'sin_sesiones' => 'There are no sessions to show.',
        'recordados' => 'Remembered devices',
        'recordados_ayuda' => 'Where «Remember me» was checked when signing in. They can sign in again without a password until the date shown.',
        'sin_dispositivos' => 'There are no remembered devices.',
        'usuario' => 'User',
        'dispositivo' => 'Device',
        'ultimo_uso' => 'Last used',
        'caduca' => 'Expires',
        'acciones' => 'Actions',
        'esta' => 'This session',
        'cerrar' => 'Close',
        'olvidar' => 'Forget',
        'cerrar_demas' => 'Close my other sessions and devices',
        'cerradas' => 'Your other sessions and devices were closed.',
        'dias' => 'Days a device is remembered',
        'dias_ayuda' => 'With 0 none is remembered, and any existing ones are forgotten.',
        'guardar' => 'Save',
        'guardado' => 'Saved.',
    ],
    'dos_pasos' => [
        'quitar' => 'Remove two-step verification',
        'quitar_texto' => 'For someone who lost their phone and recovery codes. They can sign in with just their password until they turn it on again in their profile.',
        'quitada' => 'Two-step verification was removed for :usuario.',
    ],
    'auditoria' => [
        'exportar' => 'Export CSV',
        'plazo' => 'How long it is kept',
        'dias' => 'Days',
        'dias_ayuda' => 'The oldest entries are deleted automatically. At least 30 days.',
        'plazo_guardado' => 'Saved.',
    ],
];
