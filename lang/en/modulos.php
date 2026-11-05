<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Modules',
    'explicacion' => 'Turn on only what you use: the rest leaves the menu and stays out of the way. Turning a module off does not delete its data, and if you turn it on again, everything is still there. While it is off, its pages do not open and its tasks stop.',
    'siempre' => 'Always there: Today, calendar, bookings, guests, users, backups, updates, privacy and the source code link.',
    'guardar' => 'Save',
    'guardado' => 'Modules saved.',
    'repetir' => 'Answer the questions again',
    'nuevo' => 'New',
    'requiere' => 'Needs: :modulos',
    'omitido_al_apagar' => 'Not sent because the module was turned off.',
    'aviso_nuevos' => 'New modules arrived with the update: :modulos. They arrive turned off.',
    'ver' => 'See modules',

    'grupos' => [
        'propiedades' => 'Properties',
        'reservas' => 'Bookings',
        'operacion' => 'Operations',
        'dinero' => 'Money',
        'sistema' => 'System',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Several properties', 'descripcion' => 'Manage more than one place to stay from this installation.'],
        'subordinacion' => ['nombre' => 'Subordination', 'descripcion' => 'Copy the bookings from another system that remains the main one: HotelDruid, AlojaFlow 1.2.x or another AlojaFlow installation. It is never written to. It also lets you share your properties with another installation.'],
        'motor' => ['nombre' => 'Booking engine', 'descripcion' => 'A page where guests look for dates and book on their own.'],
        'pagos' => ['nombre' => 'Online payments', 'descripcion' => 'Charge with Stripe or Mercado Pago from the booking engine.'],
        'ical' => ['nombre' => 'External calendars', 'descripcion' => 'Block dates using Airbnb, Booking and Vrbo calendars, and give them yours.'],
        'cupones' => ['nombre' => 'Coupons', 'descripcion' => 'Discount codes for bookings.'],
        'grupos' => ['nombre' => 'Groups', 'descripcion' => 'Book several units together and handle them as one.'],
        'limpieza' => ['nombre' => 'Housekeeping and maintenance', 'descripcion' => 'Status of each unit, tasks for the staff and cleaning rules.'],
        'aviso_equipo' => ['nombre' => 'Arrivals and departures notice', 'descripcion' => 'A report for the team with who leaves, who arrives and what gets cleaned, ready to send by WhatsApp.'],
        'plano' => ['nombre' => 'Property map', 'descripcion' => 'See the units laid out on a drawing of your place.'],
        'llegada' => ['nombre' => 'Online check-in', 'descripcion' => 'The guest fills in their details and accepts the documents before arriving.'],
        'mensajes' => ['nombre' => 'Messages to guests', 'descripcion' => 'Templates, sending by email and WhatsApp, and automatic messages.'],
        'caja' => ['nombre' => 'Cash and end of day', 'descripcion' => 'Cash boxes, money movements and the daily closing.'],
        'facturacion' => ['nombre' => 'Invoices and receipts', 'descripcion' => 'Tax details, invoices, receipts and exports for the accountant.'],
        'informes' => ['nombre' => 'Reports and indicators', 'descripcion' => 'Occupancy, income and other reports.'],
        'integraciones' => ['nombre' => 'Notices to other systems', 'descripcion' => 'Send what happens with bookings to other programs (webhooks). If you do not know what this is, you do not need it.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'You already have :n more property. It will keep working, but you will not be able to create others.|You already have :n more properties. They will keep working, but you will not be able to create others.',
        'subordinacion' => ':n property depends on this: it will stop updating.|:n properties depend on this: they will stop updating.',
        'ical' => ':n calendar will stop updating, and the portals will no longer be able to read yours.|:n calendars will stop updating, and the portals will no longer be able to read yours.',
        'grupos' => 'You already have :n group. Its bookings stay, but the groups screen will not open.|You already have :n groups. Their bookings stay, but the groups screen will not open.',
        'integraciones' => ':n notice will stop being sent.|:n notices will stop being sent.',
    ],

    'preguntas' => [
        'titulo' => 'Let us set it up for you',
        'explicacion' => 'Five quick questions to turn on only what serves you. You can change it later in System → Modules.',
        'paso' => 'Question :paso of :total',
        'atras' => 'Back',
        'siguiente' => 'Next',
        'terminar' => 'Finish',
        'saltar' => 'Skip and leave everything on',
        'listo' => 'Done. You can change it whenever you like in System → Modules.',
        'propiedades' => [
            'titulo' => 'How many places to stay will you manage here?',
            'una' => 'One',
            'una_ayuda' => 'A hotel, some cabins, a house or several rooms in one place.',
            'varias' => 'Several',
            'varias_ayuda' => 'Different places, each with its own units and data.',
        ],
        'otro_sistema' => [
            'titulo' => 'Do you already use another system that will remain the main one?',
            'no' => 'No, AlojaFlow will be my system',
            'no_ayuda' => 'You enter your bookings, rates and guests here.',
            'principal' => 'Yes, HotelDruid or AlojaFlow 1.2.x',
            'principal_ayuda' => 'AlojaFlow copies its bookings and adds its own on top, without ever writing to it.',
            'alojaflow' => 'Yes, another AlojaFlow installation',
            'alojaflow_ayuda' => 'This one copies a property from the other with a connection code, without ever writing to it.',
        ],
        'reservas' => [
            'titulo' => 'What else do you need for your bookings?',
            'ayuda' => 'The ones you take by phone, by WhatsApp or in person can always be entered.',
        ],
        'operacion' => [
            'titulo' => 'What do you want to organise here?',
            'ayuda' => 'Tick what you do every day, or what you would like to stop keeping on paper.',
        ],
        'dinero' => [
            'titulo' => 'How do you handle money and paperwork?',
            'ayuda' => 'Payments on each booking are always recorded. This is what gets added on top.',
        ],
        'resumen' => [
            'titulo' => 'This is how it looks',
            'ayuda' => 'If something does not convince you, go back. You can also change it later on.',
            'prendidos' => 'On',
            'apagados' => 'Off',
            'base' => 'Today, calendar, bookings, guests and the rest of the essentials',
        ],
    ],

    'pasos' => [
        'titulo' => 'First steps',
        'avance' => ':hechos of :total done',
        'ocultar' => 'Do not show again',
        'ir' => 'Go',
        'copiar' => 'Copy',
        'copiado' => 'Copied',
        'propiedad' => ['titulo' => 'Create your property', 'ayuda' => 'The name, country and currency of your place.'],
        'unidades' => ['titulo' => 'Add your units', 'ayuda' => 'The rooms, cabins or apartments you rent.'],
        'tarifa' => ['titulo' => 'Set a rate', 'ayuda' => 'What you charge per night, with its seasons if you have them.'],
        'motor' => ['titulo' => 'Open the booking engine', 'ayuda' => 'So guests can book on their own from your site.'],
        'correo' => ['titulo' => 'Set up email', 'ayuda' => 'Without email, messages do not go out and passwords cannot be recovered.'],
        'dos_pasos' => ['titulo' => 'Turn on two-step verification', 'ayuda' => 'Your account can reach everything: it is the one most worth protecting.'],
        'cron' => ['titulo' => 'Create the scheduled task', 'ayuda' => 'It makes synchronisation, messages and backups run on time. In your hosting panel, create a scheduled task (cron) at the shortest interval it allows, with this command. If you skip it, those tasks run while someone is using the system.'],
        'copia' => ['titulo' => 'Make your first backup', 'ayuda' => 'And keep it outside the hosting.'],
        'equipo' => ['titulo' => 'Invite your team', 'ayuda' => 'Each person with their own user and only what they need to see. If you work alone, ignore this step.'],
    ],
];
