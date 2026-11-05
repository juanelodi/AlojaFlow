<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Housekeeping',
    'tarea_salida' => 'Clean :unidad (check-out)',
    'tarea_estancia' => 'Clean :unidad (guest staying)',
    'solo_mias' => 'Only mine',
    'vacio' => 'Nothing to show.',
    'sale_hoy' => 'Leaving today.',
    'llega_hoy' => 'Arriving today.',
    'alojada' => 'Guest staying.',
    'libre' => 'Free.',
    'fuera_de_servicio' => 'Out of service',
    'urgente_corto' => 'Urgent',
    'ver_foto' => 'Photo :n',
    'empezar' => 'Start',
    'terminar' => 'Finish',
    'marcar_limpia' => 'Mark clean',
    'marcar_sucia' => 'Mark dirty',
    'volver_servicio' => 'Back in service',
    'poner_fuera' => 'Out of service',
    'marcada' => ':unidad: :estado.',
    'terminada' => 'Done: :tarea.',
    'incidencia' => 'Report a problem',
    'incidencia_de' => 'Problem in :unidad',
    'incidencia_reportar' => 'Report',
    'incidencia_titulo' => 'What is wrong?',
    'incidencia_titulo_ayuda' => 'For example: the shower is leaking.',
    'incidencia_descripcion' => 'Details',
    'incidencia_ok' => 'Problem reported.',
    'fotos' => 'Photos',
    'urgente' => 'It is urgent',
    'dejar_fuera' => 'Take the unit out of service (it cannot be sold)',
    'aviso_whatsapp' => 'Send by WhatsApp',
    'aviso_titulo' => ':propiedad · housekeeping for :fecha',
    'aviso_vacio' => 'No tasks.',
    'tareas' => [
        'modelo' => 'task',
        'modelos' => 'Tasks',
        'tipo' => 'Type',
        'titulo' => 'Task',
        'descripcion' => 'Description',
        'fecha' => 'Date',
        'estado' => 'Status',
        'unidad' => 'Unit',
        'asignada' => 'Assigned to',
        'sin_asignar' => 'Unassigned',
        'urgente' => 'Urgent',
        'vacio' => 'No tasks.',
        'abiertas' => 'To do',
    ],
    'regla' => [
        'accion' => 'Cleaning during the stay',
        'frecuencia' => 'How often?',
        'sin_limpieza' => 'Only at check-out',
        'dias' => 'Days',
        'fechas' => 'Dates',
        'guardada' => 'Stay cleaning saved.',
    ],
    'inspeccion' => 'Inspect units after cleaning them',
    'inspeccion_ayuda' => 'A cleaned unit stays «To inspect» until someone checks it.',
];
