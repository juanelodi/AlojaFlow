<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Long stays',
        'seccion_ayuda' => 'Weekly price from 7 nights and monthly price from 28. A full month costs this whether it has 28 or 31 nights, and any extra nights are charged at one thirtieth each. Leave them empty to charge per night.',
        'semana' => 'Weekly price',
        'mes' => 'Monthly price',
        'temporada_ayuda' => 'A season without these prices charges its nightly price, even on a long stay; you can give it its own.',
    ],
    'impuesto' => [
        'exento' => 'Do not charge from (nights)',
        'exento_ayuda' => 'A stay that reaches this many nights does not pay it. Some places exempt guests staying 30 nights or more this way; check with your accountant. Empty: always charged.',
    ],
    'motor' => [
        'semana' => 'Per week: :precio',
        'mes' => 'Per month: :precio',
        'por_persona' => 'per person',
        'aplicado' => [
            'semana' => 'With weekly price',
            'mes' => 'With monthly price',
            'temporada' => 'With full-season price',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Payment schedule',
        'vacio' => 'No payment schedule',
        'vacio_ayuda' => 'For a long stay: splits the total into monthly or weekly payments, with reminders and a receipt for each payment.',
        'periodo' => 'from :desde to :hasta',
        'vence' => 'Due',
        'generar' => 'Generate schedule',
        'generar_ayuda' => 'Splits the booking total into equal payments, and the last one covers whatever does not fill a period. Replaces the existing payments. Amounts paid are applied to the earliest due first.',
        'cada_cuanto' => 'How often they pay',
        'cada_mes' => 'Every month',
        'cada_semana' => 'Every week',
        'primer_vencimiento' => 'First due date',
        'generadas' => '{0} Nothing left to split.|{1} Schedule ready, with :count payment.|[2,*] Schedule ready, with :count payments.',
        'agregar' => 'Add payment',
        'editar' => 'Edit',
        'quitar' => 'Remove',
        'concepto' => 'Description',
        'concepto_ayuda' => 'How the guest sees it, for example “Deposit”.',
        'no_cuadra' => 'Note: the schedule adds up to :suma and the booking total is :total. Adjust the payments or generate the schedule again.',
        'saldo' => 'Stay balance',
        'recordatorio' => 'Hello :nombre: this is :propiedad, reminding you of your payment of :importe (:periodo), due on :fecha. Thank you!',
        'estados' => [
            'pagada' => 'Paid',
            'atrasada' => 'Overdue',
            'parcial' => 'Partial',
            'pendiente' => 'Pending',
        ],
    ],
    'publico' => [
        'titulo' => 'Your payments',
        'periodo' => 'Period',
        'vence' => 'Due on :fecha',
    ],
    'pagina' => [
        'titulo' => 'Monthly payments',
        'ayuda' => 'What long stays with a payment schedule still owe. AlojaFlow reminds; you decide how to collect.',
        'recordatorios' => 'Email reminders',
        'recordatorios_ayuda' => 'Sends the guest an email 3 days before each payment and another the day after if it has not arrived. You can change the days and the text in Automations and Templates.',
        'recordatorios_prender' => 'Turn on reminders',
        'recordatorios_ok' => 'Reminders turned on.',
        'vencidas' => 'Overdue',
        'proximas' => 'In the next :dias days',
        'sin_vencidas' => 'Nothing overdue.',
        'sin_proximas' => 'Nothing coming due.',
        'falta' => 'Still owed',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Payment reminder',
            'asunto' => 'Your next payment at {propiedad}',
            'cuerpo' => "Hello {nombre},\n\nThis is a reminder of your payment of {cuota_importe} ({cuota_periodo}), due on {cuota_vence}.\n\n{enlace_mi_reserva}\n\nThank you.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Overdue payment',
            'asunto' => 'Your payment at {propiedad} is still pending',
            'cuerpo' => "Hello {nombre},\n\nYour payment of {cuota_importe} ({cuota_periodo}) was due on {cuota_vence} and we have not seen it yet. If you have already paid, please send us your receipt and ignore this message.\n\n{enlace_mi_reserva}\n\nThank you.\n{propiedad}",
        ],
    ],
];
