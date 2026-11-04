<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Reserve a sua estadia',
    'idioma' => 'Idioma',
    'buscar' => 'Pesquisar',
    'reservar' => 'Reservar',
    'capacidad' => 'Até :personas pessoas',
    'quedan' => '{1} resta 1|[2,*] restam :count',
    'sin_resultados' => 'Sem disponibilidade para essas datas. Experimente outras.',
    'tus_datos' => 'Os seus dados',
    'n_personas' => ':personas pessoas',
    'anticipo' => 'Para garantir a reserva é pedido um sinal de :porcentaje %.',
    'no_llenar' => 'Não preencha este campo',
    'nombre' => 'Nome',
    'apellido' => 'Apelido',
    'correo' => 'E-mail',
    'telefono' => 'Telefone',
    'extras' => 'Extras',
    'cupon' => 'Código de desconto',
    'comentario' => 'Comentários (hora de chegada, pedidos…)',
    'condiciones' => 'as condições',
    'acepto' => 'Aceito as condições da reserva e o uso dos meus dados para a gerir.',
    'confirmar' => 'Fazer a reserva',
    'volver' => 'Voltar',
    'origen' => 'Motor de reservas',
    'concepto' => 'Reserva :numero em :propiedad',
    'tu_reserva' => 'A sua reserva',
    'estado_confirmada' => 'A sua reserva está confirmada. Esperamos por si!',
    'estado_cancelada' => 'Esta reserva está cancelada.',
    'estado_pendiente' => 'Recebemos o seu pedido. Avisaremos assim que o confirmarmos.',
    'estado_pagar' => 'O seu lugar está reservado até :fecha. Pague o sinal para confirmar a reserva.',
    'pagar_titulo' => 'Pagar :importe',
    'pagar_ayuda' => 'Abre-se a página segura da empresa de pagamentos. Os dados do seu cartão não passam por este site.',
    'pagar_con' => [
        'stripe' => 'Pagar com cartão (Stripe)',
        'mercadopago' => 'Pagar com Mercado Pago',
    ],
    'instrucciones' => 'Como pagar',
    'pie' => 'Reservas com AlojaFlow',
    'error' => [
        'cerrado' => 'As reservas online não estão disponíveis neste momento.',
        'fechas' => 'Verifique as datas: a saída deve ser posterior à chegada.',
        'fecha_pasada' => 'Essa data de chegada já não pode ser reservada online.',
        'sin_lugar' => 'Já não há lugar com essa tarifa para essas datas.',
        'estancia_minima' => 'Estadia mínima: :noches noites.',
        'pasarela' => 'Não foi possível abrir o pagamento. Tente novamente dentro de alguns minutos.',
        'nada_que_cobrar' => 'Esta reserva não tem nada a pagar online.',
    ],
];
