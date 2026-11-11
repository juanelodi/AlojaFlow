<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'La comisión se calcula sobre',
    'comision_base_ayuda' => 'Revisa tu contrato: unos portales la cobran sobre el total y otros sin impuestos.',
    'cargos' => 'Otros cargos y retenciones',
    'cargos_ayuda' => 'Lo que el canal te quita además de su comisión. Sirve para saber la utilidad bruta y neta de cada reserva.',
    'anadir' => 'Añadir cargo',
    'nombre' => 'Nombre',
    'clase' => 'Tipo',
    'calculo' => 'Cómo se calcula',
    'valor' => 'Valor (% o importe)',
    'base' => 'Sobre qué',
    'base_ayuda' => 'Tarifa base o final: mira cómo lo calcula el portal en tu contrato o estado de cuenta.',
    'sugerir' => 'Sugerir cargos de un portal',
    'sugerir_ayuda' => 'Agrega los cargos habituales de ese portal para que los ajustes. Son una referencia: tu contrato manda. Los que dependen de tu cuenta llegan apagados.',
    'plataforma' => 'Portal',
    'elige_propiedad' => 'Primero elige la propiedad.',
    'sugeridos' => [
        'pago_booking' => 'Cargo por pago (solo con Payments by Booking.com)',
        'visibilidad_booking' => 'Genius o Socio Preferente',
        'pago_expedia' => 'Cargo por cobro de Expedia',
        'visibilidad_expedia' => 'Programas de visibilidad',
        'iva_comision' => 'IVA sobre comisión y cargos',
        'retencion_isr' => 'Retención de ISR (con RFC; sin RFC, 20 %)',
        'retencion_iva' => 'Retención de IVA (con RFC; sin RFC, 16 %)',
    ],
    'utilidad' => [
        'titulo' => 'Utilidad',
        'ingreso' => 'Paga el huésped',
        'impuestos' => 'Impuestos cobrados',
        'bruta' => 'Bruta',
        'neta' => 'Neta',
        'ayuda' => 'Con los cargos actuales del canal. Las retenciones suelen acreditarse en tu declaración: consúltalo con tu contador.',
        'sin_canal' => 'El origen de esta reserva no coincide con un canal: solo se resta su comisión.',
    ],
];
