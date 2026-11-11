<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Plano',
    'vacio' => 'Todavía no hay un plano. Crea uno y coloca tus habitaciones.',
    'nuevo' => 'Nuevo plano',
    'nombre' => 'Plano',
    'nombre_ayuda' => 'Por ejemplo: Planta baja.',
    'imagen' => 'Imagen del plano (opcional)',
    'imagen_ayuda' => 'Un dibujo o una foto. Sin imagen, las habitaciones se colocan sobre un fondo liso.',
    'creado' => 'Plano creado: arrastra cada habitación a su lugar.',
    'editar' => 'Colocar habitaciones',
    'terminar' => 'Terminar',
    'editar_ayuda' => 'Arrastra cada habitación a su lugar. Se guarda sola.',
    'borrar' => 'Borrar este plano',
    'estados' => ['libre' => 'Libre', 'llega' => 'Llega hoy', 'sale' => 'Sale hoy', 'ocupada' => 'Ocupada'],
    'colores' => [
        'modelo' => 'color de cliente',
        'modelos' => 'Colores de clientes',
        'ayuda' => 'Pinta en el calendario las reservas según un dato de la ficha del huésped, como «Tipo de cliente: Empresa».',
        'campo' => 'Campo de la ficha',
        'campo_ayuda' => 'El nombre del campo personalizado, tal como está en la ficha.',
        'valor' => 'Valor',
        'color' => 'Color',
        'vacio' => 'Todavía no hay colores.',
    ],
];
