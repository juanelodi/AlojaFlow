<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Este dado vem da instalação principal e só pode ser alterado lá.',
    'redireccion' => 'A instalação principal respondeu com um redirecionamento para :destino. Verifique o endereço.',
    'version_no_admitida' => 'A instalação principal tem o esquema :version, que o AlojaFlow não suporta (suporta 3.08 e 3.081).',
    'copia_cortada' => 'A cópia chegou cortada: falta o final.',
    'falta_tabla' => 'Falta à cópia a tabela :tabla.',
    'destino' => [
        'url_invalida' => 'O endereço da instalação principal não é válido.',
        'sin_barra_final' => 'O endereço deve apontar para a pasta da instalação e terminar em «/».',
        'solo_https' => 'O endereço deve começar por https://.',
        'sin_resolver' => 'O servidor :host não foi encontrado.',
        'red_local' => 'O servidor :host está numa rede local; só é permitido se a instalação o autorizar e a instalação principal tiver ativado «Permitir rede local».',
    ],
    'respondio' => 'Aconteceu :paso, e a resposta foi: «:detalle».',
    'pasos' => [
        'probar' => 'ao testar a ligação',
        'validar' => 'ao verificar o endereço',
        'crear' => 'ao pedir a cópia',
        'descargar' => 'ao descarregar a cópia',
        'borrar' => 'ao apagar a cópia',
        'descomprimir' => 'ao descomprimir a cópia',
        'leer' => 'ao ler a cópia',
        'interno' => 'ao processar a cópia',
    ],
    'errores' => [
        'SUB-001' => 'Não foi possível ligar à instalação principal.',
        'SUB-002' => 'O certificado HTTPS da instalação principal não é válido.',
        'SUB-003' => 'A instalação principal rejeitou o utilizador ou a palavra-passe, o utilizador não pode criar cópias ou tem a verificação em dois passos ativada. A sincronização fica em pausa para não bloquear a conta.',
        'SUB-005' => 'A instalação principal atingiu o seu limite diário de cópias. Retoma amanhã; convém baixar a frequência.',
        'SUB-006' => 'O ano consultado não existe na instalação principal.',
        'SUB-007' => 'A instalação principal não consegue escrever a cópia na sua pasta de dados.',
        'SUB-008' => 'A cópia excede o tamanho máximo permitido ou chegou incompleta.',
        'SUB-009' => 'A cópia comprimida chegou danificada.',
        'SUB-010' => 'A resposta não é uma cópia válida do HotelDruid.',
        'SUB-011' => 'A versão do esquema da instalação principal não é compatível.',
        'SUB-012' => 'A cópia está incompleta.',
        'SUB-013' => 'Não foi possível apagar a cópia na instalação principal.',
        'SUB-014' => 'Já há uma sincronização desta instalação principal em curso.',
        'SUB-015' => 'A instalação principal demorou demasiado a responder.',
        'SUB-016' => 'O endereço da instalação principal não é permitido.',
        'SUB-017' => 'A outra instalação não respondeu como esperado. Verifique se tem o AlojaFlow 1.0.12 ou posterior e o módulo de subordinação ligado.',
        'SUB-018' => 'A outra instalação rejeitou o código de conexão. Pode ter sido revogado: peça um novo.',
        'SUB-098' => 'O AlojaFlow não conseguiu processar o que recebeu da instalação principal. A falha é do AlojaFlow, não da instalação principal: ficou anotada no registo.',
        'SUB-099' => 'A instalação principal deu uma resposta que o AlojaFlow não reconhece.',
    ],

    'compartir' => [
        'titulo' => 'Compartilhar com outra instalação',
        'ayuda' => 'Outra instalação do AlojaFlow pode copiar esta propriedade, só para leitura: as reservas continuam sendo alteradas aqui. Fotos e arquivos nunca viajam.',
        'sin_conexiones' => 'Você ainda não a compartilha com ninguém',
        'nombre' => 'Nome',
        'nombre_ayuda' => 'Para reconhecê-la, por exemplo «Escritório central».',
        'documentos' => 'Incluir documentos de identidade',
        'documentos_ayuda' => 'Desligado, não viajam nem o número nem os outros dados do documento. Ligue só se a outra instalação precisar.',
        'ultimo_uso' => 'Último uso',
        'estado' => 'Estado',
        'activa' => 'Ativa',
        'revocada' => 'Revogada',
        'crear' => 'Compartilhar',
        'revocar' => 'Revogar',
        'revocar_texto' => 'A outra instalação não poderá mais copiar. O que já copiou fica lá.',
        'codigo_titulo' => 'Código de conexão',
        'codigo_texto' => 'Copie e cole na outra instalação, ao criar a propriedade subordinada. Por segurança, não é mostrado de novo.',
        'codigo_listo' => 'Já copiei',
    ],

    'formulario' => [
        'tipo' => 'De qual sistema copiar',
        'codigo' => 'Código de conexão',
        'codigo_ayuda' => 'É gerado na outra instalação: ao editar a propriedade, em «Compartilhar com outra instalação». Se já estiver conectada, deixe vazio para manter o atual.',
        'codigo_invalido' => 'Esse não é um código de conexão do AlojaFlow. Copie-o completo.',
    ],
];
