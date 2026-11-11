<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

// Lo que escriben los comandos de consola.
return [
    'calendarios_leidos' => 'Calendarios leídos: :cuantos',
    'mensajes' => 'Eventos: :eventos · Por fecha: :generados · Enviados: :enviados',
    'administrador_listo' => 'Administrador listo: :correo',
    'tareas_nuevas' => 'Tareas nuevas: :cuantas',
    'ejecuciones_borradas' => 'Ejecuciones de sincronización borradas: :cuantas',
    'plazos' => 'Plazos de conservación: :detalle',
    'copias_borradas' => 'Copias de actualizaciones borradas: :cuantas',
    'vencidas_canceladas' => 'Reservas vencidas canceladas: :cuantas',
    'demo_no_activa' => 'Esta instalación no está en modo demo: no se borra nada.',
    'demo_reiniciada' => 'Demostración reiniciada.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md no tiene la sección «## :version».',
        'de' => 'Paquete de AlojaFlow :version',
        'no_pasa' => 'El paquete no pasa la revisión: :errores',
        'instalador' => 'Instalador',
        'instalador_completo' => 'Instalador con el zip dentro',
        'archivos' => 'Archivos',
        'tamano' => 'Tamaño',
        'tamano_valor' => ':zip (:descomprimido al descomprimir)',
        'listo' => 'Paquete listo y revisado.',
        'para_actualizar' => 'Para actualizar una instalación: súbelo en Sistema → :pantalla, o en la consola:',
        'para_instalar' => 'Para instalar desde cero: sube :archivo al hosting y ábrelo en el navegador.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'AlojaFlow ya estaba instalado aquí: no se tocó nada.',
        'idioma' => 'El idioma debe ser uno de: :idiomas.',
        'lista' => 'AlojaFlow instalado. Super administrador: :correo.',
        'enlace' => 'Entrada de un solo uso (caduca en 30 minutos):',
    ],
    'enlace_entrada' => [
        'sin_super' => 'No hay ningún super administrador activo con ese correo.',
        'dos_pasos' => 'Ese super administrador usa la verificación en dos pasos: el enlace no le sirve, entra con su contraseña.',
        'ya_tiene_contrasena' => 'Ese super administrador ya creó su contraseña y entra con ella. El equipo de AlojaFlow entra como soporte, con el permiso del cliente.',
    ],
    'tareas_pendientes' => [
        'nada' => 'Nada pendiente.',
        'tarea' => 'Tarea',
        'estado' => 'Estado',
        'ventana' => 'La ventana va de 1 a 1440 minutos.',
    ],
];
