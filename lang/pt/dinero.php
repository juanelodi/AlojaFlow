<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
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
