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
        'instalador_completo' => 'Installer with the zip inside',
        'archivos' => 'Files',
        'tamano' => 'Size',
        'tamano_valor' => ':zip (:descomprimido unpacked)',
        'listo' => 'Package ready and reviewed.',
        'para_actualizar' => 'To update an installation: upload it in System → :pantalla, or from the console:',
        'para_instalar' => 'To install from scratch: upload :archivo to the hosting and open it in the browser.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'AlojaFlow was already installed here: nothing was changed.',
        'idioma' => 'The language must be one of: :idiomas.',
        'lista' => 'AlojaFlow installed. Super administrator: :correo.',
        'enlace' => 'One-time sign-in link (expires in 30 minutes):',
    ],
    'enlace_entrada' => [
        'sin_super' => 'There is no active super administrator with that email.',
        'dos_pasos' => 'That super administrator uses two-step verification: the link does not work for them, they sign in with their password.',
        'ya_tiene_contrasena' => 'That super administrator has already created their password and signs in with it. The AlojaFlow team signs in as support, with the client\'s permission.',
    ],
    'tareas_pendientes' => [
        'nada' => 'Nothing pending.',
        'tarea' => 'Task',
        'estado' => 'Status',
        'ventana' => 'The window must be between 1 and 1440 minutes.',
    ],
];
