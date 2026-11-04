<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'calendarios_leidos' => 'Calendars read: :cuantos',
    'mensajes' => 'Events: :eventos · By date: :generados · Sent: :enviados',
    'administrador_listo' => 'Administrator ready: :correo',
    'tareas_nuevas' => 'New tasks: :cuantas',
    'ejecuciones_borradas' => 'Synchronization runs deleted: :cuantas',
    'plazos' => 'Retention periods: :detalle',
    'copias_borradas' => 'Update backups deleted: :cuantas',
    'vencidas_canceladas' => 'Expired bookings cancelled: :cuantas',
    'demo_no_activa' => 'This installation is not in demo mode: nothing is deleted.',
    'demo_reiniciada' => 'Demo restarted.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md has no “## :version” section.',
        'de' => 'AlojaFlow package :version',
        'no_pasa' => 'The package does not pass the review: :errores',
        'instalador' => 'Installer',
        'archivos' => 'Files',
        'tamano' => 'Size',
        'tamano_valor' => ':zip (:descomprimido unpacked)',
        'listo' => 'Package ready and reviewed.',
        'para_actualizar' => 'To update an installation: upload it in System → :pantalla, or from the console:',
        'para_instalar' => 'To install from scratch: upload :archivo to the hosting and open it in the browser.',
    ],
];
