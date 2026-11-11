<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Duplicate records',
        'ayuda' => 'Records that may be the same person. Review them before merging: bookings, contacts and notes move to the one you keep, and it cannot be undone.',
        'vacio' => 'No duplicate records were found.',
        'contacto' => 'Contact',
        'reservas' => 'Bookings',
        'conservar' => 'Merge into this one',
        'confirmar' => 'The other records in this group will be merged into :nombre and deleted. This cannot be undone.',
        'motivos' => [
            'nombre' => 'Same name',
            'correo' => 'Same email',
            'telefono' => 'Same phone',
            'documento' => 'Same document',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Anonymise by age',
        'ayuda' => 'People who have not stayed for years. Their personal data is deleted and their bookings stay under «Anonymised», so statistics still add up. Documents already saved do not change, and anything copied from a main installation is anonymised there.',
        'anios' => 'Years without stays',
        'encontrados' => '{0} Nobody has been away that long|{1} :n person has been away that long|[2,*] :n people have been away that long',
        'vacio' => 'Nothing to anonymise with this period.',
        'accion' => 'Anonymise all',
        'confirmar' => '{1} :n record without stays in :anios years will be anonymised. This cannot be undone.|[2,*] :n records without stays in :anios years will be anonymised, up to 200 at a time. This cannot be undone.',
        'hecho' => '{1} :n record anonymised.|[0,*] :n records anonymised.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Client type',
        'ayuda' => 'Free data on this record, such as «Type: VIP». If it matches a colour in Client colours, their bookings are marked that way on the calendar.',
        'agregar' => 'Add data',
    ],
    'franja' => [
        'dias' => 'Days to copy',
        'dias_ayuda' => 'With none ticked, every day.',
        'desde' => 'Copy from',
        'hasta' => 'Until',
        'ayuda' => 'In the property’s time. It can cross midnight, such as 22:00 to 06:00. Empty, at any time. Copying by hand has no such limit.',
    ],
    'region' => [
        'campo' => 'State or region',
        'ayuda' => 'It is used to suggest the matching lodging tax.',
    ],
    'colores' => [
        'titulo' => 'Calendar colours',
        'ayuda' => 'They only change for you. Empty, the usual colour.',
    ],
    'registro' => [
        'modelo' => 'tourism registration',
        'modelos' => 'Tourism registrations',
        'ayuda' => 'The registration or licence number of the property or of a unit. It is shown on public pages and documents, and AlojaFlow warns you 30 days before it expires.',
        'toda' => 'Whole property',
        'tipo' => 'Type',
        'tipo_ayuda' => 'What it is known as: RNT, permit, licence…',
        'numero' => 'Number',
        'vigente_hasta' => 'Valid until',
        'vigente_hasta_ayuda' => 'Empty if it does not expire.',
        'mostrar' => 'Show to the public',
        'mostrar_ayuda' => 'On public pages and documents.',
        'vacio' => 'No registrations yet',
        'responsables' => 'Responsible person and complaints',
        'responsables_ayuda' => 'What some countries require to be visible. Shown next to the registration number.',
        'responsable' => 'Responsible person',
        'autoridad_quejas' => 'Authority for complaints',
        'autoridad_quejas_ayuda' => 'For example, in Mexico, Profeco and the Tourism Secretariat.',
    ],
];
