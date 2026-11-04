<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'fila_protegida' => 'Este dado vem da instalação principal (HotelDruid) e só pode ser alterado lá.',
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
        'SUB-099' => 'A instalação principal deu uma resposta que o AlojaFlow não reconhece.',
    ],
];
