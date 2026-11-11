<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Módulos',
    'explicacion' => 'Ligue só o que usa: o resto sai do menu e não atrapalha. Desligar um módulo não apaga os dados dele e, se voltar a ligá-lo, está tudo lá. Enquanto estiver desligado, as páginas dele não abrem e as tarefas param.',
    'siempre' => 'Sempre presentes: Hoje, calendário, reservas, hóspedes, usuários, cópias de segurança, atualizações, privacidade e o link para o código-fonte.',
    'guardar' => 'Salvar',
    'guardado' => 'Módulos salvos.',
    'repetir' => 'Responder de novo às perguntas',
    'nuevo' => 'Novo',
    'requiere' => 'Precisa de: :modulos',
    'omitido_al_apagar' => 'Não enviado porque o módulo foi desligado.',
    'aviso_nuevos' => 'Chegaram módulos novos com a atualização: :modulos. Chegam desligados.',
    'ver' => 'Ver módulos',

    'grupos' => [
        'propiedades' => 'Propriedades',
        'reservas' => 'Reservas',
        'operacion' => 'Operação',
        'dinero' => 'Dinheiro',
        'sistema' => 'Sistema',
    ],

    'lista' => [
        'varias_propiedades' => ['nombre' => 'Várias propriedades', 'descripcion' => 'Gerenciar mais de um alojamento a partir desta instalação.'],
        'subordinacion' => ['nombre' => 'Subordinação', 'descripcion' => 'Copiar as reservas de outro sistema que continua sendo o principal: HotelDruid, AlojaFlow 1.2.x ou outra instalação do AlojaFlow. Nunca se escreve nele. Também serve para compartilhar as suas propriedades com outra instalação.'],
        'motor' => ['nombre' => 'Motor de reservas', 'descripcion' => 'Uma página onde os hóspedes procuram datas e reservam sozinhos.'],
        'pagos' => ['nombre' => 'Cobranças on-line', 'descripcion' => 'Cobrar com Stripe ou Mercado Pago a partir do motor de reservas.'],
        'ical' => ['nombre' => 'Calendários externos', 'descripcion' => 'Bloquear datas com os calendários do Airbnb, Booking e Vrbo, e dar-lhes o seu.'],
        'cupones' => ['nombre' => 'Cupons', 'descripcion' => 'Códigos de desconto para as reservas.'],
        'grupos' => ['nombre' => 'Grupos', 'descripcion' => 'Reservar várias unidades juntas e tratá-las como uma só.'],
        'limpieza' => ['nombre' => 'Limpeza e manutenção', 'descripcion' => 'Estado de cada unidade, tarefas para a equipe e regras de limpeza.'],
        'aviso_equipo' => ['nombre' => 'Aviso de entradas e saídas', 'descripcion' => 'Um boletim para a equipe com quem sai, quem chega e o que limpar, pronto para enviar por WhatsApp.'],
        'plano' => ['nombre' => 'Planta da propriedade', 'descripcion' => 'Ver as unidades dispostas em um desenho do seu alojamento.'],
        'llegada' => ['nombre' => 'Chegada on-line', 'descripcion' => 'O hóspede preenche os dados e aceita os documentos antes de chegar.'],
        'mensajes' => ['nombre' => 'Mensagens aos hóspedes', 'descripcion' => 'Modelos, envios por e-mail e WhatsApp, e mensagens automáticas.'],
        'camping' => ['nombre' => 'Parques de campismo', 'descripcion' => 'O que tem cada lugar, o equipamento do hóspede, escolher na planta, leituras de eletricidade e água, residentes sazonais e extras limitados.'],
        'propietarios' => ['nombre' => 'Proprietários', 'descripcion' => 'Se gere unidades de terceiros: proprietários e comissão, despesas, extratos, o seu portal e as suas estadias.'],
        'documentos' => ['nombre' => 'Documentos e campos próprios', 'descripcion' => 'Os seus documentos com os dados de cada reserva, como um contrato ou a lista de chegadas, e o que quiser guardar de hóspedes e reservas.'],
        'estancias_largas' => ['nombre' => 'Estadias longas e mensalidades', 'descripcion' => 'Preço por semana e por mês, e calendário de pagamentos com lembretes.'],
        'portal' => ['nombre' => 'A minha reserva e guia digital', 'descripcion' => 'Uma página para cada hóspede, com a reserva e o que precisa de saber, e um código QR em cada unidade.'],
        'bitacora' => ['nombre' => 'Diário de turnos', 'descripcion' => 'O que um turno deixa para o seguinte. O importante aparece em Hoje.'],
        'empresas' => ['nombre' => 'Empresas e agências', 'descripcion' => 'Quem reserva ou paga por seus hóspedes, com suas condições e extrato de conta.'],
        'opiniones' => ['nombre' => 'Opiniões dos hóspedes', 'descripcion' => 'Uma pesquisa após a estadia, com link para sua página de avaliações.'],
        'caja' => ['nombre' => 'Caixa e fechamento do dia', 'descripcion' => 'Caixas, movimentos de dinheiro e o fechamento diário.'],
        'informes' => ['nombre' => 'Relatórios e indicadores', 'descripcion' => 'Ocupação, receitas e outros relatórios.'],
        'integraciones' => ['nombre' => 'Avisos a outros sistemas', 'descripcion' => 'Enviar o que acontece com as reservas a outros programas (webhooks). Se não sabe o que é, não precisa disso.'],
    ],

    'en_uso' => [
        'varias_propiedades' => 'Você já tem mais :n propriedade. Ela continuará funcionando, mas não poderá criar outras.|Você já tem mais :n propriedades. Elas continuarão funcionando, mas não poderá criar outras.',
        'subordinacion' => ':n propriedade depende disto: deixará de se atualizar.|:n propriedades dependem disto: deixarão de se atualizar.',
        'ical' => ':n calendário deixará de se atualizar, e os portais não poderão mais ler os seus.|:n calendários deixarão de se atualizar, e os portais não poderão mais ler os seus.',
        'grupos' => 'Você já tem :n grupo. As reservas dele continuam lá, mas a tela de grupos não abrirá.|Você já tem :n grupos. As reservas deles continuam lá, mas a tela de grupos não abrirá.',
        'integraciones' => ':n aviso deixará de ser enviado.|:n avisos deixarão de ser enviados.',
    ],

    'preguntas' => [
        'titulo' => 'Vamos deixar do seu jeito',
        'explicacion' => 'Cinco perguntas rápidas para ligar só o que lhe serve. Pode mudar depois em Sistema → Módulos.',
        'paso' => 'Pergunta :paso de :total',
        'atras' => 'Voltar',
        'siguiente' => 'Seguinte',
        'terminar' => 'Concluir',
        'saltar' => 'Pular e deixar tudo ligado',
        'listo' => 'Pronto. Pode mudar quando quiser em Sistema → Módulos.',
        'propiedades' => [
            'titulo' => 'Quantos alojamentos vai gerenciar aqui?',
            'una' => 'Um',
            'una_ayuda' => 'Um hotel, algumas cabanas, uma casa ou vários quartos em um mesmo lugar.',
            'varias' => 'Vários',
            'varias_ayuda' => 'Alojamentos diferentes, cada um com suas unidades e seus dados.',
        ],
        'otro_sistema' => [
            'titulo' => 'Já usa outro sistema que continuará sendo o principal?',
            'no' => 'Não, o AlojaFlow será o meu sistema',
            'no_ayuda' => 'Aqui você registra suas reservas, tarifas e hóspedes.',
            'principal' => 'Sim, HotelDruid ou AlojaFlow 1.2.x',
            'principal_ayuda' => 'O AlojaFlow copia as reservas dele e acrescenta o que é seu, sem nunca escrever nele.',
            'alojaflow' => 'Sim, outra instalação do AlojaFlow',
            'alojaflow_ayuda' => 'Esta copia uma propriedade da outra com um código de conexão, sem nunca escrever nela.',
        ],
        'reservas' => [
            'titulo' => 'Do que mais precisa para as suas reservas?',
            'ayuda' => 'As que recebe por telefone, por WhatsApp ou pessoalmente sempre podem ser registradas.',
        ],
        'operacion' => [
            'titulo' => 'O que quer organizar aqui?',
            'ayuda' => 'Marque o que faz todos os dias, ou o que gostaria de deixar de controlar em papel.',
        ],
        'dinero' => [
            'titulo' => 'Como controla o dinheiro e os papéis?',
            'ayuda' => 'Os pagamentos de cada reserva sempre são anotados. Isto é o que se acrescenta.',
        ],
        'resumen' => [
            'titulo' => 'Assim fica',
            'ayuda' => 'Se algo não o convence, volte atrás. Também pode mudar mais adiante.',
            'prendidos' => 'Ligado',
            'apagados' => 'Desligado',
            'base' => 'Hoje, calendário, reservas, hóspedes e o restante do essencial',
        ],
    ],

    'pasos' => [
        'titulo' => 'Primeiros passos',
        'avance' => ':hechos de :total prontos',
        'ocultar' => 'Não mostrar mais',
        'ir' => 'Ir',
        'copiar' => 'Copiar',
        'copiado' => 'Copiado',
        'propiedad' => ['titulo' => 'Crie a sua propriedade', 'ayuda' => 'O nome, o país e a moeda do seu alojamento.'],
        'unidades' => ['titulo' => 'Adicione as suas unidades', 'ayuda' => 'Os quartos, cabanas ou apartamentos que aluga.'],
        'tarifa' => ['titulo' => 'Defina uma tarifa', 'ayuda' => 'Quanto cobra por noite, com as temporadas se as tiver.'],
        'motor' => ['titulo' => 'Abra o motor de reservas', 'ayuda' => 'Para que os hóspedes reservem sozinhos a partir do seu site.'],
        'correo' => ['titulo' => 'Configure o e-mail', 'ayuda' => 'Sem e-mail as mensagens não saem e não é possível recuperar uma senha.'],
        'dos_pasos' => ['titulo' => 'Ative a verificação em duas etapas', 'ayuda' => 'A sua conta tem acesso a tudo: é a que mais convém proteger.'],
        'cron' => ['titulo' => 'Crie a tarefa agendada', 'ayuda' => 'Faz com que as sincronizações, as mensagens e as cópias rodem na hora certa. No painel da sua hospedagem, crie uma tarefa agendada (cron) com o intervalo mais curto permitido, com este comando. Sem ela, essas tarefas rodam quando alguém usa o sistema.'],
        'copia' => ['titulo' => 'Faça a sua primeira cópia de segurança', 'ayuda' => 'E guarde-a fora da hospedagem.'],
        'equipo' => ['titulo' => 'Convide a sua equipe', 'ayuda' => 'Cada um com o seu usuário e só com o que precisa ver. Se trabalha sozinho, ignore este passo.'],
    ],
];
