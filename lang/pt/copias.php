<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'titulo' => 'Cópias de segurança',
    'explicacion' => 'Cada cópia guarda toda a base de dados e os ficheiros carregados, num zip que pode descarregar e voltar a carregar aqui ou noutra instalação.',
    'estado_automaticas' => 'É criada uma cópia automática todas as noites e são conservadas as últimas :cuantas.',
    'estado_manuales' => 'As cópias automáticas estão desligadas: só são criadas quando as pedir.',
    'aviso_fuera' => 'Estas cópias ficam neste mesmo servidor. Descarregue uma de vez em quando e guarde-a noutro local, juntamente com o ficheiro de configuração: é a única coisa que o protege se o servidor se perder.',
    'crear' => 'Criar cópia agora',
    'creada' => 'Cópia criada: :nombre',
    'subir' => 'Carregar uma cópia',
    'subir_ayuda' => 'Carregue um zip descarregado antes, desta ou de outra instalação do AlojaFlow. É apenas guardado: para o usar, clique depois em «Restaurar».',
    'archivo' => 'Ficheiro da cópia (zip)',
    'subida' => 'Cópia carregada: :nombre',
    'ajustes' => 'Definições',
    'automaticas' => 'Criar uma cópia automática todas as noites',
    'automaticas_ayuda' => 'Precisa da tarefa agendada do servidor ou de que alguém use o sistema nesse dia.',
    'conservar' => 'Quantas cópias conservar',
    'conservar_ayuda' => 'Ao criar uma nova, as mais antigas são apagadas. Cada cópia contém dados pessoais: não guarde mais do que precisa.',
    'ajustes_guardados' => 'Definições guardadas.',
    'descargar_env' => 'Descarregar configuração',
    'descargar_env_ayuda' => 'O ficheiro .env tem a chave com que se cifram os documentos e palavras-passe guardados, e a palavra-passe da base de dados. Sem essa chave, uma cópia restaurada noutro servidor não poderá ler esses dados. Guarde-o num local seguro, separado das cópias.',
    'contrasena' => 'A sua palavra-passe',
    'lista' => 'Cópias guardadas',
    'vacio' => 'Ainda não há cópias.',
    'fecha' => 'Data',
    'origen' => 'Origem',
    'version' => 'Versão',
    'tamano' => 'Tamanho',
    'acciones' => 'Ações',
    'otra_clave' => 'De outra instalação: os seus dados cifrados não poderão ser lidos com a chave atual.',
    'origenes' => [
        'manual' => 'Manual',
        'programada' => 'Automática',
        'antes_de_restaurar' => 'Antes de restaurar',
    ],
    'descargar' => 'Descarregar',
    'restaurar' => 'Restaurar',
    'restaurar_titulo' => 'Restaurar esta cópia?',
    'restaurar_texto' => 'Todos os dados atuais são substituídos pelos da cópia. Antes é guardada outra cópia do estado atual, caso seja preciso voltar atrás. No fim terá de iniciar sessão novamente.',
    'eliminar' => 'Apagar',
    'eliminar_titulo' => 'Apagar esta cópia?',
    'eliminada' => 'Cópia apagada.',
    'error' => [
        'no_escribe' => 'Não foi possível escrever a cópia em storage/app/private/copias. Verifique as permissões e o espaço livre.',
        'no_existe' => 'Essa cópia já não existe.',
        'no_es_copia' => 'Esse ficheiro não é uma cópia de segurança do AlojaFlow ou está danificado.',
        'mas_nueva' => 'Esta cópia vem da versão :version, mais recente do que a instalada. Atualize o AlojaFlow antes de a restaurar.',
        'restaurar' => 'Não foi possível restaurar a cópia; os dados voltaram ao estado anterior. Detalhe: :detalle',
    ],
];
