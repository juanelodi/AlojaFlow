<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'Commission is calculated on',
    'comision_base_ayuda' => 'Check your contract: some portals charge it on the total, others before taxes.',
    'cargos' => 'Other charges and withholdings',
    'cargos_ayuda' => 'What the channel takes besides its commission. Used to know the gross and net profit of each booking.',
    'anadir' => 'Add charge',
    'nombre' => 'Name',
    'clase' => 'Type',
    'calculo' => 'How it is calculated',
    'valor' => 'Value (% or amount)',
    'base' => 'Calculated on',
    'base_ayuda' => 'Base or final rate: see how the portal calculates it in your contract or statement.',
    'sugerir' => 'Suggest a portal’s charges',
    'sugerir_ayuda' => 'Adds the usual charges of that portal for you to adjust. They are a reference: your contract rules. Those depending on your account arrive turned off.',
    'plataforma' => 'Portal',
    'elige_propiedad' => 'Choose the property first.',
    'sugeridos' => [
        'pago_booking' => 'Payment charge (only with Payments by Booking.com)',
        'visibilidad_booking' => 'Genius or Preferred Partner',
        'pago_expedia' => 'Expedia collection charge',
        'visibilidad_expedia' => 'Visibility programmes',
        'iva_comision' => 'VAT on commission and charges',
        'retencion_isr' => 'Income tax withholding (with tax ID; without, 20%)',
        'retencion_iva' => 'VAT withholding (with tax ID; without, 16%)',
    ],
    'utilidad' => [
        'titulo' => 'Profit',
        'ingreso' => 'Guest pays',
        'impuestos' => 'Taxes collected',
        'bruta' => 'Gross',
        'neta' => 'Net',
        'ayuda' => 'With the channel’s current charges. Withholdings can usually be credited on your tax return: ask your accountant.',
        'sin_canal' => 'The source of this booking does not match a channel: only its commission is subtracted.',
    ],
];
