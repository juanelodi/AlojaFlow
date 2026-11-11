<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Suporte do AlojaFlow',
    'explicacion' => 'Por padrão, ninguém da equipe do AlojaFlow entra no seu sistema. Se você pedir ajuda, pode permitir que o suporte entre por 24 horas. Quem atender entra com o próprio nome, tudo o que fizer fica na auditoria e, ao fim do prazo, o usuário é desativado sozinho.',
    'permitir' => 'Permitir que o suporte do AlojaFlow entre por 24 horas',
    'extender' => 'Estender a permissão por 24 horas a partir de agora',
    'permitir_ayuda' => 'A equipe do AlojaFlow poderá entrar como administrador, sem mudar os módulos nem os administradores, pelas próximas 24 horas. Você pode retirar a permissão quando quiser.',
    'permitido' => 'Pronto: o suporte pode entrar até :hasta.',
    'retirar' => 'Retirar a permissão',
    'retirar_ayuda' => 'Quem do suporte estiver dentro sai na hora e não poderá entrar de novo.',
    'retirado' => 'Permissão retirada.',
    'sin_permiso_estado' => 'O suporte não tem permissão para entrar.',
    'permitido_estado' => 'O suporte pode entrar até :hasta.',
    'accesos' => 'Acessos do suporte (últimos 90 dias)',
    'sin_accesos' => 'O suporte não entrou.',
    'quien' => 'Quem',
    'cuando' => 'Quando',
    'hasta' => 'Até',
    'motivo' => 'Motivo',
    'dentro' => 'Dentro agora',
    'con_permiso' => 'Com sua permissão',
    'emergencia' => 'Emergência: :motivo',
    'usuario' => 'Suporte AlojaFlow — :nombre',
    'solo_instancia' => 'O acesso do suporte só existe no serviço hospedado do AlojaFlow.',
    'sin_permiso' => 'O cliente não deu permissão ao suporte. Sem permissão só se entra em uma emergência, com o motivo (--emergencia).',
    'nombre_invalido' => 'Falta o nome de quem atende (até 80 caracteres).',
    'minutos_invalidos' => 'Os minutos vão de 1 a :maximo.',
    'emergencia_asunto' => 'O suporte do AlojaFlow entrou no seu sistema por uma emergência',
    'emergencia_cuerpo' => "Olá,\n\n:nombre, do suporte do AlojaFlow, entrou no seu sistema sem a sua permissão por uma emergência.\n\nMotivo: :motivo\n\nO acesso termina em :hasta. Tudo o que fizer fica na auditoria (Sistema → Auditoria). Se tiver dúvidas, responda a este e-mail ou escreva para o suporte.",
    'consola' => [
        'listo' => 'Acesso pronto para :usuario até :hasta. O link funciona uma vez e expira em 5 minutos.',
    ],
];
