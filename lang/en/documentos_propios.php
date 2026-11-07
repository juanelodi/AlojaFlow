<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'modelo' => 'document',
    'modelos' => 'Own documents',
    'nombre' => 'Name',
    'alcance' => 'What it comes from',
    'alcance_ayuda' => 'One for a booking is created from that booking, under “More actions”. One for several, from this list, choosing the dates.',
    'activo' => 'Active',
    'cuerpo' => 'Document text',
    'vacio' => 'No documents yet',
    'vacio_ayuda' => 'A contract, a registration sheet or the day’s arrivals list, with your own text. You can start with the starter ones.',
    'variables' => 'Write the document like any text. Where you want a piece of data, put its variable in braces, like {huesped}. For an empty language, the property’s is used.',
    'accion' => 'Own document',
    'elegir' => 'Document',
    'generar' => 'Generate',
    'vista_previa' => 'Preview',
    'sin_reservas_previa' => 'That property has no bookings to try it with yet.',
    'desde' => 'From',
    'hasta' => 'To',
    'criterio' => 'Which bookings',
    'criterios' => [
        'llegadas' => 'Those arriving on those dates',
        'salidas' => 'Those leaving on those dates',
        'en_casa' => 'Those staying on those dates',
    ],
    'demasiadas' => 'That is more than :maximo bookings for one document. Choose fewer days.',
    'de_partida' => 'Create the starter ones',
    'de_partida_ayuda' => 'Creates a contract, a registration sheet and an arrivals list for you to adapt. The contract is only a starting point: review it with your adviser.',
    'de_partida_listo' => 'Done: review and adapt them.',
    'si' => 'Yes',

    'campos' => [
        'modelo' => 'own field',
        'modelos' => 'Own fields',
        'seccion' => 'Own fields',
        'accion' => 'Own fields',
        'guardados' => 'Fields saved.',
        'nombre' => 'Name',
        'nombre_ayuda' => 'For example: car plate, preferred room.',
        'entidad' => 'What it belongs to',
        'tipo' => 'What it stores',
        'activo' => 'Active',
        'activo_ayuda' => 'An inactive one is no longer asked for, and what was saved is kept.',
        'variable' => 'Variable',
        'vacio' => 'No own fields yet',
        'vacio_ayuda' => 'Whatever you want to keep about a guest or a booking that the system does not ask for. It shows in their records, in documents and in messages.',
    ],

    'base' => [
        'contrato' => [
            'nombre' => 'Accommodation contract',
            'partes' => 'Between {propiedad}, located at {domicilio_propiedad}, and {huesped}, with ID {huesped_documento}, the following is agreed:',
            'estancia' => 'Stay in {unidad}, from {llegada} to {salida}: {noches} nights, for {personas} people.',
            'precio' => 'Total price: {total}. Paid to date: {pagado}. Still to pay: {pendiente}.',
            'personas' => 'Only the people registered in this booking may stay.',
            'danos' => 'The guest is liable for any damage caused to the unit.',
            'firma' => 'Signed on {hoy}:',
        ],
        'registro' => [
            'nombre' => 'Registration sheet',
            'intro' => 'Booking {numero} · {unidad} · from {llegada} to {salida}.',
            'persona' => 'Person',
            'nacimiento' => 'Date of birth',
            'nacionalidad' => 'Nationality',
            'documento' => 'ID document',
            'firma' => 'Signature of {huesped}:',
        ],
        'llegadas' => [
            'nombre' => 'Arrivals list',
            'intro' => 'From {desde} to {hasta}: {cuantas} bookings.',
            'llegada' => 'Arrival',
            'salida' => 'Departure',
            'unidad' => 'Unit',
            'huesped' => 'Guest',
            'personas' => 'People',
            'pendiente' => 'Still to pay',
        ],
    ],
];
