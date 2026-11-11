<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'AlojaFlow support',
    'explicacion' => 'By default, no one from the AlojaFlow team enters your system. If you ask for help, you can let support in for 24 hours. Whoever helps you signs in under their own name, everything they do is recorded in the audit log, and when the time is up their user is deactivated automatically.',
    'permitir' => 'Allow AlojaFlow support to sign in for 24 hours',
    'extender' => 'Extend the permission 24 hours from now',
    'permitir_ayuda' => 'The AlojaFlow team will be able to sign in as an administrator, without changing modules or administrators, for the next 24 hours. You can withdraw the permission at any time.',
    'permitido' => 'Done: support can sign in until :hasta.',
    'retirar' => 'Withdraw permission',
    'retirar_ayuda' => 'Anyone from support who is signed in is signed out right away and cannot sign in again.',
    'retirado' => 'Permission withdrawn.',
    'sin_permiso_estado' => 'Support is not allowed to sign in.',
    'permitido_estado' => 'Support can sign in until :hasta.',
    'accesos' => 'Support sign-ins (last 90 days)',
    'sin_accesos' => 'Support has not signed in.',
    'quien' => 'Who',
    'cuando' => 'When',
    'hasta' => 'Until',
    'motivo' => 'Reason',
    'dentro' => 'Signed in now',
    'con_permiso' => 'With your permission',
    'emergencia' => 'Emergency: :motivo',
    'usuario' => 'AlojaFlow Support — :nombre',
    'solo_instancia' => 'Support access only exists in the AlojaFlow hosted service.',
    'sin_permiso' => 'The customer has not allowed support in. Without permission, sign-in is only possible in an emergency, with the reason (--emergencia).',
    'nombre_invalido' => 'The name of the person helping is missing (up to 80 characters).',
    'minutos_invalidos' => 'Minutes must be between 1 and :maximo.',
    'emergencia_asunto' => 'AlojaFlow support signed in to your system because of an emergency',
    'emergencia_cuerpo' => "Hello,\n\n:nombre, from AlojaFlow support, signed in to your system without your permission because of an emergency.\n\nReason: :motivo\n\nThe access ends on :hasta. Everything they do is recorded in the audit log (System → Audit). If you have questions, reply to this email or write to support.",
    'consola' => [
        'listo' => 'Access ready for :usuario until :hasta. The link works once and expires in 5 minutes.',
    ],
];
