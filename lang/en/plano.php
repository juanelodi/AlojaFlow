<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Floor plan',
    'vacio' => 'There is no floor plan yet. Create one and place your rooms.',
    'nuevo' => 'New floor plan',
    'nombre' => 'Floor plan',
    'nombre_ayuda' => 'For example: Ground floor.',
    'imagen' => 'Floor plan image (optional)',
    'imagen_ayuda' => 'A drawing or a photo. Without an image, rooms are placed on a plain background.',
    'creado' => 'Floor plan created: drag each room to its place.',
    'editar' => 'Place rooms',
    'terminar' => 'Finish',
    'editar_ayuda' => 'Drag each room to its place. It saves automatically.',
    'borrar' => 'Delete this floor plan',
    'estados' => ['libre' => 'Free', 'llega' => 'Arriving today', 'sale' => 'Leaving today', 'ocupada' => 'Occupied'],
    'colores' => [
        'modelo' => 'guest color',
        'modelos' => 'Guest colors',
        'ayuda' => 'Colors bookings in the calendar according to a field on the guest record, such as «Guest type: Company».',
        'campo' => 'Record field',
        'campo_ayuda' => 'The name of the custom field, exactly as on the record.',
        'valor' => 'Value',
        'color' => 'Color',
        'vacio' => 'No colors yet.',
    ],
];
