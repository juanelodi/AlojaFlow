<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Limpieza',
    'tarea_salida' => 'Limpiar :unidad (salida)',
    'tarea_estancia' => 'Limpiar :unidad (huésped alojado)',
    'solo_mias' => 'Solo lo mío',
    'vacio' => 'No hay nada que mostrar.',
    'sale_hoy' => 'Sale hoy.',
    'llega_hoy' => 'Llega hoy.',
    'alojada' => 'Huésped alojado.',
    'libre' => 'Libre.',
    'fuera_de_servicio' => 'Fuera de servicio',
    'urgente_corto' => 'Urgente',
    'ver_foto' => 'Foto :n',
    'empezar' => 'Empezar',
    'terminar' => 'Terminar',
    'marcar_limpia' => 'Marcar limpia',
    'marcar_sucia' => 'Marcar sucia',
    'volver_servicio' => 'Volver a servicio',
    'poner_fuera' => 'Fuera de servicio',
    'marcada' => ':unidad: :estado.',
    'terminada' => 'Listo: :tarea.',
    'incidencia' => 'Reportar problema',
    'incidencia_de' => 'Problema en :unidad',
    'incidencia_reportar' => 'Reportar',
    'incidencia_titulo' => '¿Qué pasa?',
    'incidencia_titulo_ayuda' => 'Por ejemplo: gotea la regadera.',
    'incidencia_descripcion' => 'Detalles',
    'incidencia_ok' => 'Problema reportado.',
    'fotos' => 'Fotos',
    'urgente' => 'Es urgente',
    'dejar_fuera' => 'Dejar la unidad fuera de servicio (no se podrá vender)',
    'aviso_whatsapp' => 'Mandar por WhatsApp',
    'aviso_titulo' => ':propiedad · limpieza del :fecha',
    'aviso_vacio' => 'Sin tareas.',
    // Panel de tareas.
    'tareas' => [
        'modelo' => 'tarea',
        'modelos' => 'Tareas',
        'tipo' => 'Tipo',
        'titulo' => 'Tarea',
        'descripcion' => 'Descripción',
        'fecha' => 'Fecha',
        'estado' => 'Estado',
        'unidad' => 'Unidad',
        'asignada' => 'Asignada a',
        'sin_asignar' => 'Sin asignar',
        'urgente' => 'Urgente',
        'vacio' => 'No hay tareas.',
        'abiertas' => 'Por hacer',
    ],
    // Reserva.
    'regla' => [
        'accion' => 'Limpieza durante la estancia',
        'frecuencia' => '¿Cada cuándo?',
        'sin_limpieza' => 'Solo a la salida',
        'dias' => 'Días',
        'fechas' => 'Días',
        'guardada' => 'Limpieza de la estancia guardada.',
    ],
    'inspeccion' => 'Revisar las unidades después de limpiarlas',
    'inspeccion_ayuda' => 'Una unidad limpiada queda «Por revisar» hasta que alguien la inspeccione.',
];
