<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'No disponible',
    'error_formato' => 'La dirección no devolvió un calendario iCal válido.',
    'error_tamano' => 'El calendario es demasiado grande.',
    'calendario_externo' => 'Ocupado en :fuente',
    'fuentes' => [
        'modelo' => 'calendario de un portal',
        'modelos' => 'Calendarios iCal',
        'ayuda' => 'Pega el enlace iCal que da cada portal (Airbnb, Booking.com, Vrbo…). Esas fechas dejan de venderse aquí y, si chocan con una reserva, se te avisa.',
        'unidad' => 'Habitación',
        'nombre' => 'Nombre',
        'nombre_ayuda' => 'Por ejemplo: Airbnb.',
        'canal' => 'Canal',
        'url' => 'Enlace iCal del portal',
        'url_ayuda' => 'Se guarda cifrado porque lleva una clave del portal.',
        'url_invalida' => 'Esa dirección no se permite: debe ser pública.',
        'frecuencia' => 'Leer cada',
        'activa' => 'Activo',
        'estado' => 'Estado',
        'ultima_lectura' => 'Última lectura',
        'vacio' => 'Todavía no hay calendarios de portales.',
        'leer' => 'Leer ahora',
        'leida' => 'Calendario leído: :n fechas ocupadas.',
        'no_leida' => 'No se pudo leer el calendario',
    ],
    'exportar' => [
        'accion' => 'Enlaces para los portales',
        'propiedad' => 'Propiedad',
        'ayuda' => 'Pega en cada portal el enlace de su habitación. Solo enseña fechas ocupadas: nunca datos de huéspedes.',
        'vacio' => 'Esta propiedad no tiene habitaciones.',
        'regenerar' => 'Cambiar los enlaces',
        'regenerar_ayuda' => 'Los enlaces actuales dejarán de funcionar y tendrás que pegar los nuevos en cada portal. Úsalo si alguien que no debe los tiene.',
        'regenerados' => 'Enlaces cambiados.',
    ],
    'choques' => [
        'modelo' => 'choque',
        'modelos' => 'Choques',
        'desde' => 'Desde',
        'hasta' => 'Hasta',
        'unidad' => 'Habitación',
        'reserva' => 'Reserva',
        'reserva_n' => 'Reserva :numero',
        'con' => 'Choca con',
        'nota' => 'Nota',
        'abiertos' => 'Por resolver',
        'vacio' => 'No hay choques: ninguna habitación está ocupada dos veces.',
        'resolver' => 'Marcar resuelto',
    ],
];
