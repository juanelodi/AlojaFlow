<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'aviso' => [
        'titulo' => 'Aviso de entradas e saídas',
        'desde' => 'A partir de',
        'dias' => 'Dias',
        'anadir' => 'Adicionar a cada unidade',
        'nombre' => 'Nome',
        'personas' => 'Pessoas',
        'origen' => 'Origem',
        'noches' => 'Noites',
        'telefono' => 'Telefone',
        'comentario' => 'Comentário',
        'mostrar_limpieza' => 'Mostrar limpeza',
        'texto' => 'Texto do aviso (pode ajustá-lo antes de enviar)',
        'telefono_destino' => 'WhatsApp de quem recebe',
        'salen' => 'Saem',
        'llegan' => 'Chegam',
        'limpieza' => 'Limpeza',
        'n_personas' => ':count pessoa|:count pessoas',
        'sin_movimiento' => 'Sem movimento nestas datas.',
        'omitidos_ayuda' => 'Um cliente interno ou de manutenção não deve aparecer aqui? Marque-o em',
    ],

    'omisiones' => [
        'modelo' => 'cliente omitido',
        'modelos' => 'Clientes omitidos',
        'ayuda' => 'Escolha onde este cliente não deve aparecer: por exemplo, os seus familiares ou as estadias de manutenção. Nada é apagado, e a reserva continua ocupando as datas no calendário.',
        'por' => 'Quem?',
        'por_huesped' => 'Um cliente',
        'por_tipo' => 'Todos de um tipo',
        'campo' => 'Campo da ficha',
        'campo_ayuda' => 'O nome do campo personalizado, como nas cores do calendário. Por exemplo, «Tipo de cliente».',
        'valor' => 'Valor',
        'donde' => 'Onde é omitido?',
        'en_aviso' => 'Aviso de entradas e saídas',
        'en_aviso_ayuda' => 'Não aparece no aviso para a equipe.',
        'en_informes' => 'Relatórios e indicadores',
        'en_informes_ayuda' => 'As noites e receitas dele não contam. As cobranças e o caixa sim, porque esse dinheiro é real.',
        'en_mensajes' => 'Mensagens automáticas',
        'en_mensajes_ayuda' => 'As automações não chegam a ele. As que você enviar à mão, sim.',
        'motivo' => 'Motivo',
        'quien' => 'Cliente',
        'vacio' => 'Não há clientes omitidos',
    ],

    'listas' => [
        'modelo' => 'lista de verificação',
        'modelos' => 'Listas de verificação',
        'ayuda' => 'O que deve ser revisado em cada tipo de tarefa. Aparece como caixas na tela de Limpeza, nas tarefas criadas de agora em diante.',
        'tipo' => 'Tipo de tarefa',
        'nombre' => 'Nome',
        'puntos' => 'Itens',
        'punto' => 'Item',
        'anadir' => 'Adicionar item',
        'vacio' => 'Não há listas de verificação',
    ],

    'recurrentes' => [
        'modelo' => 'manutenção recorrente',
        'modelos' => 'Manutenção recorrente',
        'ayuda' => 'Trabalhos que se repetem, como trocar filtros ou dedetizar. A tarefa é criada uma semana antes.',
        'unidad_ayuda' => 'Deixe vazio se for de todo o lugar.',
        'titulo' => 'Trabalho',
        'descripcion' => 'Descrição',
        'cada_dias' => 'A cada quantos dias',
        'proxima' => 'Próxima data',
        'vacio' => 'Não há manutenção recorrente',
    ],
];
