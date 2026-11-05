<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'duplicados' => [
        'titulo' => 'Fichas duplicadas',
        'ayuda' => 'Fichas que podem ser a mesma pessoa. Reveja-as antes de as juntar: reservas, contactos e notas passam para a que conservar, e não se pode desfazer.',
        'vacio' => 'Não foram encontradas fichas duplicadas.',
        'contacto' => 'Contacto',
        'reservas' => 'Reservas',
        'conservar' => 'Fundir nesta',
        'confirmar' => 'As outras fichas deste grupo serão unidas à de :nombre e apagadas. Não se pode desfazer.',
        'motivos' => [
            'nombre' => 'Mesmo nome',
            'correo' => 'Mesmo e-mail',
            'telefono' => 'Mesmo telefone',
            'documento' => 'Mesmo documento',
        ],
    ],
    'anonimizar' => [
        'titulo' => 'Anonimizar por antiguidade',
        'ayuda' => 'Quem não vem há anos. Os dados pessoais são apagados e as reservas ficam em nome de «Anonimizado», para que as estatísticas continuem certas. Os documentos já guardados não mudam, e o que vem de uma instalação principal anonimiza-se lá.',
        'anios' => 'Anos sem estadias',
        'encontrados' => '{0} Ninguém está há tanto tempo sem vir|{1} :n pessoa está há tanto tempo sem vir|[2,*] :n pessoas estão há tanto tempo sem vir',
        'vacio' => 'Nada a anonimizar com este prazo.',
        'accion' => 'Anonimizar todos',
        'confirmar' => '{1} Será anonimizada :n ficha sem estadias há :anios anos. Não se pode desfazer.|[2,*] Serão anonimizadas :n fichas sem estadias há :anios anos, até 200 de cada vez. Não se pode desfazer.',
        'hecho' => '{1} :n ficha anonimizada.|[0,*] :n fichas anonimizadas.',
    ],
    'tipo_cliente' => [
        'titulo' => 'Tipo de cliente',
        'ayuda' => 'Dados livres desta ficha, como «Tipo: VIP». Se coincidirem com uma cor de Cores dos clientes, as suas reservas ficam assim marcadas no calendário.',
        'agregar' => 'Adicionar dado',
    ],
    'facturas' => [
        'formato' => 'Formato',
        'excel' => 'Para abrir no Excel',
        'csv' => 'CSV padrão, para outros programas (vírgula, ponto decimal)',
    ],
    'franja' => [
        'dias' => 'Dias de cópia',
        'dias_ayuda' => 'Sem nenhum marcado, todos os dias.',
        'desde' => 'Copiar a partir das',
        'hasta' => 'Até às',
        'ayuda' => 'Na hora da propriedade. Pode passar a meia-noite, como das 22:00 às 06:00. Vazio, a qualquer hora. Copiar à mão não tem este limite.',
    ],
    'region' => [
        'campo' => 'Estado ou região',
        'ayuda' => 'Serve para propor a taxa de alojamento correspondente.',
    ],
    'colores' => [
        'titulo' => 'Cores do calendário',
        'ayuda' => 'Só mudam para si. Vazio, a cor de sempre.',
    ],
    'registro' => [
        'modelo' => 'registo turístico',
        'modelos' => 'Registos turísticos',
        'ayuda' => 'O número de registo ou licença da propriedade ou de uma unidade. Aparece nas páginas públicas e nos documentos, e o AlojaFlow avisa 30 dias antes de expirar.',
        'toda' => 'Toda a propriedade',
        'tipo' => 'Tipo',
        'tipo_ayuda' => 'Como é conhecido: RNAL, RNT, licença…',
        'numero' => 'Número',
        'vigente_hasta' => 'Válido até',
        'vigente_hasta_ayuda' => 'Vazio se não expirar.',
        'mostrar' => 'Mostrar ao público',
        'mostrar_ayuda' => 'Nas páginas públicas e nos documentos.',
        'vacio' => 'Ainda sem registos',
        'responsables' => 'Responsável e reclamações',
        'responsables_ayuda' => 'O que alguns países exigem ter à vista. Aparece junto ao número de registo.',
        'responsable' => 'Responsável',
        'autoridad_quejas' => 'Autoridade para reclamações',
        'autoridad_quejas_ayuda' => 'Por exemplo, no México, a Profeco e a Secretaria de Turismo.',
    ],
];
