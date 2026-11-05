<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'modelo' => 'documento',
    'modelos' => 'Documentos próprios',
    'nombre' => 'Nome',
    'alcance' => 'De onde sai',
    'alcance_ayuda' => 'O de uma reserva cria-se a partir dessa reserva, em «Mais ações». O de várias, a partir desta lista, escolhendo as datas.',
    'activo' => 'Ativo',
    'cuerpo' => 'Texto do documento',
    'vacio' => 'Ainda não há documentos',
    'vacio_ayuda' => 'Um contrato, uma ficha de registo ou a lista de chegadas do dia, com o seu próprio texto. Pode começar pelos de partida.',
    'variables' => 'Escreva o documento como qualquer texto. Onde quiser um dado, ponha a sua variável entre chavetas, como {huesped}. Num idioma vazio usa-se o da propriedade.',
    'variables_titulo' => 'As variáveis',
    'variables_reserva' => 'Da reserva: {huesped} {nombre} {huesped_telefono} {huesped_correo} {huesped_nacimiento} {huesped_nacionalidad} {huesped_documento} {huesped_domicilio} {numero} {codigo} {llegada} {llegada_corta} {salida} {salida_corta} {noches} {personas} {ninos} {unidad} {tarifa} {origen} {comentario} {total} {pagado} {pendiente} {deposito} {propiedad} {domicilio_propiedad} {telefono_propiedad} {correo_propiedad} {hoy}.',
    'variables_huespedes' => 'Uma linha de tabela, um ponto de lista ou um parágrafo que use uma destas repete-se por cada hóspede: {h_numero} {h_nombre} {h_nacimiento} {h_nacionalidad} {h_documento_tipo} {h_documento} {h_domicilio} {h_parentesco}.',
    'variables_cargos' => 'Por cada custo: {c_nombre} {c_cantidad} {c_importe}.',
    'variables_pagos' => 'Por cada pagamento: {p_fecha} {p_metodo} {p_referencia} {p_importe}.',
    'variables_periodo' => 'Num documento de várias reservas, o que use dados de uma reserva repete-se por cada uma. Além disso: {desde} {hasta} {cuantas}.',
    'variables_campos' => 'Os seus campos próprios: {campo_…}, e na linha de cada hóspede {h_campo_…}; a lista de campos diz como se chama cada um. As variáveis das suas mensagens também valem.',
    'accion' => 'Documento próprio',
    'elegir' => 'Documento',
    'generar' => 'Gerar',
    'vista_previa' => 'Pré-visualizar',
    'sin_reservas_previa' => 'Essa propriedade ainda não tem reservas com que o experimentar.',
    'desde' => 'De',
    'hasta' => 'Até',
    'criterio' => 'Que reservas',
    'criterios' => [
        'llegadas' => 'As que chegam nessas datas',
        'salidas' => 'As que saem nessas datas',
        'en_casa' => 'As que ficam nessas datas',
    ],
    'demasiadas' => 'São mais de :maximo reservas para um só documento. Escolha menos dias.',
    'de_partida' => 'Criar os de partida',
    'de_partida_ayuda' => 'Cria um contrato, uma ficha de registo e uma lista de chegadas, para adaptar. O contrato é só um ponto de partida: reveja-o com quem o aconselha.',
    'de_partida_listo' => 'Pronto: reveja-os e adapte-os.',
    'si' => 'Sim',

    'campos' => [
        'modelo' => 'campo próprio',
        'modelos' => 'Campos próprios',
        'seccion' => 'Campos próprios',
        'accion' => 'Campos próprios',
        'guardados' => 'Campos guardados.',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Por exemplo: matrícula do carro, quarto preferido.',
        'entidad' => 'A que pertence',
        'tipo' => 'O que guarda',
        'activo' => 'Ativo',
        'activo_ayuda' => 'Um inativo já não é pedido, e o que foi guardado mantém-se.',
        'variable' => 'Variável',
        'vacio' => 'Ainda não há campos próprios',
        'vacio_ayuda' => 'O que quiser guardar de um hóspede ou de uma reserva e o sistema não pede. Aparece nas fichas, nos documentos e nas mensagens.',
    ],

    'base' => [
        'contrato' => [
            'nombre' => 'Contrato de alojamento',
            'partes' => 'Entre {propiedad}, com morada em {domicilio_propiedad}, e {huesped}, com identificação {huesped_documento}, acorda-se o seguinte:',
            'estancia' => 'Estadia em {unidad}, de {llegada} a {salida}: {noches} noites, para {personas} pessoas.',
            'precio' => 'Preço total: {total}. Pago até hoje: {pagado}. Falta pagar: {pendiente}.',
            'personas' => 'Só podem ficar as pessoas registadas nesta reserva.',
            'danos' => 'O hóspede responde pelos danos causados na unidade.',
            'firma' => 'Assinado em {hoy}:',
        ],
        'registro' => [
            'nombre' => 'Ficha de registo',
            'intro' => 'Reserva {numero} · {unidad} · de {llegada} a {salida}.',
            'persona' => 'Pessoa',
            'nacimiento' => 'Nascimento',
            'nacionalidad' => 'Nacionalidade',
            'documento' => 'Documento',
            'firma' => 'Assinatura de {huesped}:',
        ],
        'llegadas' => [
            'nombre' => 'Lista de chegadas',
            'intro' => 'De {desde} a {hasta}: {cuantas} reservas.',
            'llegada' => 'Chegada',
            'salida' => 'Saída',
            'unidad' => 'Unidade',
            'huesped' => 'Hóspede',
            'personas' => 'Pessoas',
            'pendiente' => 'Falta pagar',
        ],
    ],
];
