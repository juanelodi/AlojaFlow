<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'aviso' => [
        'titulo' => 'Aviso de entradas y salidas',
        'desde' => 'Desde',
        'dias' => 'Días',
        'anadir' => 'Añadir a cada unidad',
        'nombre' => 'Nombre',
        'personas' => 'Personas',
        'origen' => 'Origen',
        'noches' => 'Noches',
        'telefono' => 'Teléfono',
        'comentario' => 'Comentario',
        'mostrar_limpieza' => 'Mostrar limpieza',
        'texto' => 'Texto del aviso (lo puedes retocar antes de mandarlo)',
        'telefono_destino' => 'WhatsApp de quien lo recibe',
        'salen' => 'Salen',
        'llegan' => 'Llegan',
        'limpieza' => 'Limpieza',
        'n_personas' => ':count persona|:count personas',
        'sin_movimiento' => 'Sin movimiento en estas fechas.',
        'omitidos_ayuda' => '¿Un cliente interno o de mantenimiento no debe salir aquí? Márcalo en',
    ],

    'omisiones' => [
        'modelo' => 'cliente omitido',
        'modelos' => 'Clientes omitidos',
        'ayuda' => 'Elige dónde no debe salir este cliente: por ejemplo, tus familiares o las estancias de mantenimiento. No se borra nada, y la reserva sigue ocupando sus fechas en el calendario.',
        'por' => '¿A quién?',
        'por_huesped' => 'Un cliente',
        'por_tipo' => 'Todos los de un tipo',
        'campo' => 'Campo de la ficha',
        'campo_ayuda' => 'El nombre del campo personalizado, como en los colores del calendario. Por ejemplo, «Tipo de cliente».',
        'valor' => 'Valor',
        'donde' => '¿Dónde se omite?',
        'en_aviso' => 'Aviso de entradas y salidas',
        'en_aviso_ayuda' => 'No sale en el aviso para el equipo.',
        'en_informes' => 'Informes e indicadores',
        'en_informes_ayuda' => 'Sus noches e ingresos no cuentan. Los cobros y la caja sí, porque ese dinero es real.',
        'en_mensajes' => 'Mensajes automáticos',
        'en_mensajes_ayuda' => 'No le llegan las automatizaciones. Los que le mandes a mano, sí.',
        'motivo' => 'Motivo',
        'quien' => 'Cliente',
        'vacio' => 'No hay clientes omitidos',
    ],

    'listas' => [
        'modelo' => 'lista de comprobación',
        'modelos' => 'Listas de comprobación',
        'ayuda' => 'Lo que hay que revisar en cada tipo de tarea. Sale como casillas en la pantalla de Limpieza, en las tareas que se creen desde ahora.',
        'tipo' => 'Tipo de tarea',
        'nombre' => 'Nombre',
        'puntos' => 'Puntos',
        'punto' => 'Punto',
        'anadir' => 'Añadir punto',
        'vacio' => 'No hay listas de comprobación',
    ],

    'recurrentes' => [
        'modelo' => 'mantenimiento recurrente',
        'modelos' => 'Mantenimiento recurrente',
        'ayuda' => 'Trabajos que se repiten, como cambiar filtros o fumigar. Una semana antes se crea su tarea.',
        'unidad_ayuda' => 'Déjala vacía si es de todo el lugar.',
        'titulo' => 'Trabajo',
        'descripcion' => 'Descripción',
        'cada_dias' => 'Cada cuántos días',
        'proxima' => 'Próxima fecha',
        'vacio' => 'No hay mantenimientos recurrentes',
    ],
];
