<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'base' => [
        'confirmacion' => [
            'nombre' => 'Confirmação da reserva',
            'asunto' => 'A sua reserva em {propiedad} está confirmada',
            'cuerpo' => "Olá {nombre},\n\nA sua reserva {numero} em {propiedad} está confirmada.\n\nChegada: {llegada}\nSaída: {salida}\nPessoas: {personas}\nTotal: {total}\nPor pagar: {pendiente}\n\nConsulte a sua reserva aqui: {enlace_reserva}\n\nSe tiver alguma dúvida, escreva para {correo_propiedad} ou ligue para {telefono_propiedad}.\n\nEsperamos por si!\n{propiedad}",
        ],
        'solicitud' => [
            'nombre' => 'Pedido recebido',
            'asunto' => 'Recebemos o seu pedido de reserva em {propiedad}',
            'cuerpo' => "Olá {nombre},\n\nRecebemos o seu pedido para {llegada}. Confirmaremos assim que o analisarmos.\n\n{propiedad}",
        ],
        'recordatorio' => [
            'nombre' => 'Lembrete de chegada',
            'asunto' => 'Esperamos por si em {propiedad}',
            'cuerpo' => "Olá {nombre},\n\nFalta pouco para a sua chegada, em {llegada}.\n\nPor pagar: {pendiente}\n\nFaça o registo antecipado aqui: {enlace_llegada}\n\nSe tiver alguma dúvida, ligue para {telefono_propiedad}.\n\n{propiedad}",
        ],
        'gracias' => [
            'nombre' => 'Obrigado pela visita',
            'asunto' => 'Obrigado por ficar em {propiedad}',
            'cuerpo' => "Olá {nombre},\n\nObrigado por ter ficado connosco. Esperamos vê-lo em breve.\n\n{propiedad}",
        ],
        'cancelacion' => [
            'nombre' => 'Reserva cancelada',
            'asunto' => 'A sua reserva em {propiedad} foi cancelada',
            'cuerpo' => "Olá {nombre},\n\nA sua reserva {numero} de {llegada} foi cancelada. Se acha que é um erro, escreva para {correo_propiedad}.\n\n{propiedad}",
        ],
    ],
];
