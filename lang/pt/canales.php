<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'comision_base' => 'A comissão é calculada sobre',
    'comision_base_ayuda' => 'Verifique o contrato: alguns portais cobram sobre o total, outros sem impostos.',
    'cargos' => 'Outros encargos e retenções',
    'cargos_ayuda' => 'O que o canal retira além da comissão. Serve para saber o lucro bruto e líquido de cada reserva.',
    'anadir' => 'Adicionar encargo',
    'nombre' => 'Nome',
    'clase' => 'Tipo',
    'calculo' => 'Como se calcula',
    'valor' => 'Valor (% ou montante)',
    'base' => 'Calculado sobre',
    'base_ayuda' => 'Tarifa base ou final: veja como o portal a calcula no contrato ou extrato.',
    'sugerir' => 'Sugerir encargos de um portal',
    'sugerir_ayuda' => 'Adiciona os encargos habituais desse portal para ajustar. São uma referência: vale o seu contrato. Os que dependem da sua conta chegam desligados.',
    'plataforma' => 'Portal',
    'elige_propiedad' => 'Escolha primeiro a propriedade.',
    'sugeridos' => [
        'pago_booking' => 'Encargo de pagamento (só com Payments by Booking.com)',
        'visibilidad_booking' => 'Genius ou Parceiro Preferencial',
        'pago_expedia' => 'Encargo de cobrança da Expedia',
        'visibilidad_expedia' => 'Programas de visibilidade',
        'iva_comision' => 'IVA sobre comissão e encargos',
        'retencion_isr' => 'Retenção de imposto sobre o rendimento (com NIF; sem, 20 %)',
        'retencion_iva' => 'Retenção de IVA (com NIF; sem, 16 %)',
    ],
    'utilidad' => [
        'titulo' => 'Lucro',
        'ingreso' => 'Paga o hóspede',
        'impuestos' => 'Impostos cobrados',
        'bruta' => 'Bruto',
        'neta' => 'Líquido',
        'ayuda' => 'Com os encargos atuais do canal. As retenções costumam ser dedutíveis na declaração: consulte o seu contabilista.',
        'sin_canal' => 'A origem desta reserva não coincide com um canal: só se subtrai a comissão.',
    ],
];
