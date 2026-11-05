<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 juanelodi y colaboradores de AlojaFlow
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

return [
    'nombres' => [
        'operacion' => [
            'ver' => 'Ver as telas de operação (Hoje, calendário, limpeza)',
        ],
        'datos' => [
            'ver' => 'Ver reservas, hóspedes e outros dados',
        ],
        'indicadores' => [
            'ver' => 'Ver indicadores e relatórios',
        ],
        'sincronizacion' => [
            'gestionar' => 'Gerenciar a sincronização com a instalação principal',
        ],
        'reservas' => [
            'gestionar' => 'Criar e alterar reservas, hóspedes e pagamentos',
        ],
        'catalogo' => [
            'gestionar' => 'Criar e alterar unidades e tarifas',
        ],
        'limpieza' => [
            'gestionar' => 'Gerenciar limpeza e manutenção',
        ],
        'mensajes' => [
            'gestionar' => 'Enviar mensagens aos hóspedes',
        ],
        'ajustes' => [
            'gestionar' => 'Modelos, automações, calendários e motor de reservas',
        ],
        'huespedes' => [
            'gestionar' => 'Registro de chegada, documentos e privacidade',
        ],
        'facturacion' => [
            'gestionar' => 'Dados fiscais, faturas e exportações',
        ],
    ],
    'roles' => [
        'modelo' => 'função',
        'modelos' => 'Funções',
        'nombre' => 'Nome',
        'permisos' => 'Permissões',
        'en_uso' => 'Usuários com esta função',
        'ayuda' => 'O que você mudar aqui vale para todas as pessoas com esta função assim que salvar.',
    ],
    'usuario' => [
        'ajustes' => 'Ajustes desta pessoa',
        'ajustes_ayuda' => 'Opcional. Só para quem precisa de algo diferente da sua função.',
        'agregados' => 'Além da sua função, pode',
        'quitados' => 'Mesmo que a sua função permita, não pode',
        'solo_propias' => 'Somente as próprias reservas e hóspedes',
        'solo_propias_ayuda' => 'Hoje e as listas mostram só o que criou. O calendário mostra o que está ocupado, sem nomes.',
        'sin_iniciadas' => 'Não alterar reservas já iniciadas',
        'sin_iniciadas_ayuda' => 'As que já chegaram ou chegam hoje. Registrar entrada, saída e pagamentos continua permitido.',
        'horas_modificar' => 'Horas para alterar uma reserva',
        'horas_cancelar' => 'Horas para cancelar uma reserva',
        'horas_ayuda' => 'Contadas desde a criação. Vazio: sem limite.',
    ],
    'limites' => [
        'no_modificar' => 'Suas permissões não deixam alterar esta reserva.',
        'ocupada' => 'Ocupado',
    ],
    'sesiones' => [
        'titulo' => 'Sessões e dispositivos',
        'abiertas' => 'Sessões abertas',
        'sin_sesiones' => 'Não há sessões para mostrar.',
        'recordados' => 'Dispositivos lembrados',
        'recordados_ayuda' => 'Onde «Lembrar de mim» foi marcado ao entrar. Podem entrar de novo sem senha até a data indicada.',
        'sin_dispositivos' => 'Não há dispositivos lembrados.',
        'usuario' => 'Usuário',
        'dispositivo' => 'Dispositivo',
        'ultimo_uso' => 'Último uso',
        'caduca' => 'Expira',
        'acciones' => 'Ações',
        'esta' => 'Esta sessão',
        'cerrar' => 'Encerrar',
        'olvidar' => 'Esquecer',
        'cerrar_demas' => 'Encerrar minhas outras sessões e dispositivos',
        'cerradas' => 'Suas outras sessões e dispositivos foram encerrados.',
        'dias' => 'Dias em que um dispositivo é lembrado',
        'dias_ayuda' => 'Com 0 nenhum é lembrado, e os existentes são esquecidos.',
        'guardar' => 'Salvar',
        'guardado' => 'Salvo.',
    ],
    'dos_pasos' => [
        'quitar' => 'Remover a verificação em duas etapas',
        'quitar_texto' => 'Para quem perdeu o telefone e os códigos de recuperação. Poderá entrar só com a senha até ativá-la de novo no perfil.',
        'quitada' => 'A verificação em duas etapas de :usuario foi removida.',
    ],
    'auditoria' => [
        'exportar' => 'Exportar CSV',
        'plazo' => 'Tempo de conservação',
        'dias' => 'Dias',
        'dias_ayuda' => 'O mais antigo é apagado sozinho. Mínimo de 30 dias.',
        'plazo_guardado' => 'Salvo.',
    ],
];
