<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'tarifa' => [
        'seccion' => 'Estadias longas',
        'seccion_ayuda' => 'Preço por semana a partir de 7 noites e por mês a partir de 28. Um mês completo custa isto quer tenha 28 ou 31 noites, e as noites a mais cobram-se a um trinta avos. Deixe vazio para cobrar por noite.',
        'semana' => 'Preço por semana',
        'mes' => 'Preço por mês',
        'temporada_ayuda' => 'Uma época sem estes preços cobra o seu preço por noite, mesmo numa estadia longa; se quiser, dê-lhe os seus.',
    ],
    'impuesto' => [
        'exento' => 'Não cobrar a partir de (noites)',
        'exento_ayuda' => 'Uma estadia que chega a estas noites não o paga. Alguns locais isentam assim quem fica 30 noites ou mais; confirme com o seu contabilista. Vazio: cobra-se sempre.',
    ],
    'motor' => [
        'semana' => 'Por semana: :precio',
        'mes' => 'Por mês: :precio',
        'por_persona' => 'por pessoa',
        'aplicado' => [
            'semana' => 'Com preço por semana',
            'mes' => 'Com preço por mês',
            'temporada' => 'Com preço da época completa',
        ],
    ],
    'cuotas' => [
        'titulo' => 'Calendário de pagamentos',
        'vacio' => 'Sem calendário de pagamentos',
        'vacio_ayuda' => 'Para uma estadia longa: reparte o total em pagamentos mensais ou semanais, com lembretes e um recibo por pagamento.',
        'periodo' => 'de :desde a :hasta',
        'vence' => 'Vence',
        'generar' => 'Gerar calendário',
        'generar_ayuda' => 'Reparte o total da reserva em pagamentos iguais, e o último cobre o que não preencher um período. Substitui os pagamentos existentes. O que foi pago aplica-se primeiro aos que vencem antes.',
        'cada_cuanto' => 'Com que frequência paga',
        'cada_mes' => 'Todos os meses',
        'cada_semana' => 'Todas as semanas',
        'primer_vencimiento' => 'Primeiro vencimento',
        'generadas' => '{0} Nada a repartir.|{1} Calendário pronto, com :count pagamento.|[2,*] Calendário pronto, com :count pagamentos.',
        'agregar' => 'Adicionar pagamento',
        'editar' => 'Editar',
        'quitar' => 'Remover',
        'concepto' => 'Descrição',
        'concepto_ayuda' => 'Como o hóspede o vê, por exemplo «Depósito».',
        'no_cuadra' => 'Atenção: o calendário soma :suma e o total da reserva é :total. Ajuste os pagamentos ou gere o calendário novamente.',
        'saldo' => 'Saldo da estadia',
        'recordatorio' => 'Olá :nombre: escrevemos de :propiedad para lembrar o seu pagamento de :importe (:periodo), com vencimento a :fecha. Obrigado!',
        'estados' => [
            'pagada' => 'Pago',
            'atrasada' => 'Em atraso',
            'parcial' => 'Parcial',
            'pendiente' => 'Pendente',
        ],
    ],
    'publico' => [
        'titulo' => 'Os seus pagamentos',
        'periodo' => 'Período',
        'vence' => 'Vence a :fecha',
    ],
    'pagina' => [
        'titulo' => 'Mensalidades',
        'ayuda' => 'O que as estadias longas com calendário de pagamentos ainda devem. O AlojaFlow lembra; cobrar é consigo.',
        'recordatorios' => 'Lembretes por e-mail',
        'recordatorios_ayuda' => 'Envia ao hóspede um e-mail 3 dias antes de cada pagamento e outro no dia seguinte se não tiver chegado. Pode mudar os dias e o texto em Automatizações e Modelos.',
        'recordatorios_prender' => 'Ativar lembretes',
        'recordatorios_ok' => 'Lembretes ativados.',
        'vencidas' => 'Em atraso',
        'proximas' => 'Nos próximos :dias dias',
        'sin_vencidas' => 'Nada em atraso.',
        'sin_proximas' => 'Nada a vencer.',
        'falta' => 'Em falta',
    ],
    'mensajes' => [
        'cuota_por_vencer' => [
            'nombre' => 'Lembrete de pagamento',
            'asunto' => 'O seu próximo pagamento em {propiedad}',
            'cuerpo' => "Olá {nombre},\n\nLembramos o seu pagamento de {cuota_importe} ({cuota_periodo}), com vencimento a {cuota_vence}.\n\n{enlace_mi_reserva}\n\nObrigado.\n{propiedad}",
        ],
        'cuota_vencida' => [
            'nombre' => 'Pagamento em atraso',
            'asunto' => 'O seu pagamento em {propiedad} continua pendente',
            'cuerpo' => "Olá {nombre},\n\nO seu pagamento de {cuota_importe} ({cuota_periodo}) venceu a {cuota_vence} e ainda não o vemos. Se já o fez, envie-nos o comprovativo e ignore esta mensagem.\n\n{enlace_mi_reserva}\n\nObrigado.\n{propiedad}",
        ],
    ],
];
