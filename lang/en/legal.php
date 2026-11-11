<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

// Starter texts, not legal advice: the property reviews them with its lawyer before publishing.
return [
    'documentos' => [
        'ine' => 'INE (Mexican voter ID)',
        'pasaporte' => 'Passport',
        'licencia' => 'Driver\'s license',
        'fmm' => 'Migration form (FMM)',
        'dni' => 'DNI',
        'nie' => 'NIE',
        'identificacion' => 'Identity document',
        'otro' => 'Other',
    ],
    'campos' => [
        'procedencia' => 'City you are coming from',
        'motivo_viaje' => 'Reason for travel',
        'placas' => 'Vehicle license plate',
        'soporte' => 'Support number',
        'parentesco' => 'Relationship (for minors)',
    ],
    'mexico' => [
        'aviso_privacidad' => [
            'titulo' => 'Privacy notice',
            'cuerpo' => <<<'TEXTO'
{propiedad}, located at {domicilio}, is responsible for processing your personal data.

DATA WE COLLECT
Identification and contact: name, nationality, date of birth, address, email and phone. Official ID: type, number and, if you choose to send it, its photo. Details of your stay and payments. Card details are processed directly by the payment company; we do not store them.

We do not collect sensitive personal data. Please do not write it in comments.

WHAT WE USE IT FOR
Necessary purposes: managing your booking and stay, registering you as a guest, charging and handling queries.
Voluntary purposes: sending you promotions. If you do not want this, write to {correo}; it does not affect your booking.

WHO WE SHARE IT WITH
Providers who help us deliver the service (data hosting, email, payments) and authorities when the law requires it. We do not sell your data.

YOUR RIGHTS (ARCO)
You can access, rectify, cancel or object to the use of your data, and revoke your consent, by writing to {correo} or calling {telefono}. Include your name, how you prove your identity and what you request. We will respond within the legal time limits.

HOW LONG WE KEEP IT
The photo of your ID, up to 30 days after you leave. Registration data, one year. Payment data, as long as the law requires.

CHANGES
We will publish any change to this notice on our website or tell you about it when you register.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Accommodation terms',
            'cuerpo' => <<<'TEXTO'
These terms govern your stay at {propiedad}.

1. PRICE. As shown in your booking, with the taxes indicated. It is paid in the currency indicated.
2. CHECK-IN AND CHECK-OUT. At the times we inform you of. A late check-out without notice may incur a charge.
3. PEOPLE. Only registered people may stay. A valid official ID may be required on arrival.
4. CANCELLATION. The cancellation policy shown when booking applies.
5. DAMAGE. The guest is liable for damage caused to the room and furniture.
6. VALUABLES. We recommend using the safe or telling us about them on arrival.
7. COMPLAINTS. Write to {correo}. You have the right to go to the Federal Consumer Protection Agency (PROFECO).
TEXTO,
        ],
        'reglamento' => [
            'titulo' => 'House rules',
            'cuerpo' => <<<'TEXTO'
For everyone's comfort, at {propiedad} we ask you to:

- Respect other guests' rest, especially at night.
- Not smoke in the rooms.
- Register everyone staying.
- Take care of the facilities and report any damage.
- Ask before bringing pets: not all rooms allow them.
TEXTO,
        ],
        'politica_cancelacion' => [
            'titulo' => 'Cancellation policy',
            'cuerpo' => <<<'TEXTO'
You can cancel free of charge up to [number] days before arrival. After that, [percentage]% of the total is charged. If you do not show up, the first night is charged.

To cancel, write to {correo} or call {telefono} with your booking number. Refunds are made using the same payment method.
TEXTO,
        ],
    ],
    'generico' => [
        'aviso_privacidad' => [
            'titulo' => 'Privacy notice',
            'cuerpo' => <<<'TEXTO'
{propiedad} ({domicilio}) is responsible for your personal data.

We use it to manage your booking and stay, register you as a guest, charge and comply with the law. We share it only with providers who help us deliver the service and with authorities when the law requires it. We do not sell it.

The photo of your ID is kept for up to 30 days after you leave; registration data, for one year.

You can access your data, correct it or ask us to delete it by writing to {correo}.

This text is a starting point: it must be adapted to the law of your country.
TEXTO,
        ],
        'contrato_grupo' => [
            'titulo' => 'Group contract',
            'cuerpo' => <<<'TEXTO'
This contract governs the stay of the group “{grupo}” ({codigo}) at {propiedad}, coordinated by {coordinador}.

1. STAY. From {llegada} to {salida}, with {habitaciones} rooms.
2. PRICE. The total is {total}. Agreed payments:
{pagos}
3. GUEST LIST. The coordinator will say who goes in each room by {fecha_limite} at the latest.
4. CANCELLATION. The cancellation policy made known to them applies.
5. CONDITIONS. The coordinator informs the group of the property's rules. Each guest is liable for any damage they cause.
6. COMPLAINTS. Write to us at {correo}.
TEXTO,
        ],
        'contrato' => [
            'titulo' => 'Accommodation terms',
            'cuerpo' => <<<'TEXTO'
These terms govern your stay at {propiedad}.

1. The price is as shown in your booking.
2. Check-in and check-out are at the times we inform you of.
3. Only registered people may stay.
4. The cancellation policy shown when booking applies.
5. The guest is liable for any damage caused.

For any complaint, write to {correo}.
TEXTO,
        ],
        'reglamento' => [
            'titulo' => 'House rules',
            'cuerpo' => <<<'TEXTO'
For everyone's comfort, at {propiedad} we ask you to respect other guests' rest, not smoke in the rooms, register everyone staying and tell us about any damage.
TEXTO,
        ],
        'politica_cancelacion' => [
            'titulo' => 'Cancellation policy',
            'cuerpo' => <<<'TEXTO'
You can cancel free of charge up to [number] days before arrival. After that, [percentage]% of the total is charged. If you do not show up, the first night is charged. To cancel, write to {correo}.
TEXTO,
        ],
    ],
];
