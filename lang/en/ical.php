<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'ocupado' => 'Not available',
    'error_formato' => 'The address did not return a valid iCal calendar.',
    'error_tamano' => 'The calendar is too large.',
    'calendario_externo' => 'Busy on :fuente',
    'fuentes' => [
        'modelo' => 'portal calendar',
        'modelos' => 'iCal calendars',
        'ayuda' => 'Paste the iCal link from each portal (Airbnb, Booking.com, Vrbo…). Those dates stop being sold here and, if they clash with a booking, you are warned.',
        'unidad' => 'Room',
        'nombre' => 'Name',
        'nombre_ayuda' => 'For example: Airbnb.',
        'canal' => 'Channel',
        'url' => 'Portal iCal link',
        'url_ayuda' => 'Stored encrypted because it contains a key from the portal.',
        'url_invalida' => 'That address is not allowed: it must be public.',
        'frecuencia' => 'Read every',
        'activa' => 'Active',
        'estado' => 'Status',
        'ultima_lectura' => 'Last read',
        'vacio' => 'No portal calendars yet.',
        'leer' => 'Read now',
        'leida' => 'Calendar read: :n busy dates.',
        'no_leida' => 'The calendar could not be read',
    ],
    'exportar' => [
        'accion' => 'Links for the portals',
        'propiedad' => 'Property',
        'ayuda' => 'Paste in each portal the link of its room. It only shows busy dates: never guest data.',
        'vacio' => 'This property has no rooms.',
        'regenerar' => 'Change the links',
        'regenerar_ayuda' => 'The current links will stop working and you will have to paste the new ones in each portal. Use it if someone who should not have them does.',
        'regenerados' => 'Links changed.',
    ],
    'choques' => [
        'modelo' => 'clash',
        'modelos' => 'Clashes',
        'desde' => 'From',
        'hasta' => 'To',
        'unidad' => 'Room',
        'reserva' => 'Booking',
        'reserva_n' => 'Booking :numero',
        'con' => 'Clashes with',
        'nota' => 'Note',
        'abiertos' => 'To resolve',
        'vacio' => 'No clashes: no room is occupied twice.',
        'resolver' => 'Mark resolved',
    ],
];
