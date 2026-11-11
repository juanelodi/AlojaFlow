<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'segunda' => [
        'moneda' => 'Segunda moeda',
        'moneda_ayuda' => 'Opcional, como USD. Mostra-se junto aos preços e pode receber-se dinheiro nela; tudo se soma na moeda da propriedade.',
        'tipo' => 'Taxa de câmbio',
        'tipo_ayuda' => 'Quanto vale 1 :segunda em :moneda. É você quem a escreve: altere-a quando mudar.',
        'recibido_en' => 'Recebido em',
        'tipo_pago' => 'Taxa de câmbio deste pagamento',
        'se_anota' => 'Na reserva fica registado :importe.',
        'recibido' => ':importe a :tipo',
        'recibido_titulo' => 'Recebido',
        'efectivo_en' => 'Dinheiro em :moneda (conta-se à parte)',
    ],
    'politicas' => [
        'no_reembolsable' => 'Não reembolsável',
        'no_reembolsable_ayuda' => 'Se cancelarem, em qualquer momento, é cobrado o total.',
        'modelo' => 'política',
        'modelos' => 'Políticas',
        'nombre' => 'Nome',
        'dias_gratis' => 'Cancelamento grátis até (dias antes)',
        'penalizacion' => 'Penalização depois (%)',
        'no_show' => 'Penalização se não chegar (%)',
        'deposito' => 'Sinal (%)',
        'deposito_plazo' => 'Prazo para pagar o sinal (dias)',
        'texto' => 'Texto para o hóspede',
        'predeterminada' => 'Predefinida',
        'predeterminada_ayuda' => 'Aplica-se quando o canal da reserva não tem uma própria.',
        'vacio' => 'Ainda não há políticas.',
        'resumen_cancelacion' => 'Segundo a política «:politica», a penalização é de :penalizacion. Já pagou :pagado: a devolver :devolver, por cobrar :cobrar.',
        'no_show_accion' => 'Não chegou',
        'no_show_ayuda' => 'Indica que o hóspede não compareceu. Num alojamento independente, também cancela a reserva e liberta as noites.',
        'no_show_nota' => 'Nota',
        'no_show_ok' => 'Marcado como não chegou. Penalização: :importe.',
        'error_no_show' => 'Só se pode marcar no dia de chegada ou depois, e se a entrada não tiver sido registada.',
    ],
];
