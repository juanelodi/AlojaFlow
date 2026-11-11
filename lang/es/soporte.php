<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'titulo' => 'Soporte de AlojaFlow',
    'explicacion' => 'Por omisión, nadie del equipo de AlojaFlow entra a tu sistema. Si pides ayuda, puedes permitir que el soporte entre durante 24 horas. Quien atienda entra con su nombre, todo lo que haga queda en la auditoría y, al terminar el plazo, su usuario se desactiva solo.',
    'permitir' => 'Permitir que el soporte de AlojaFlow entre durante 24 horas',
    'extender' => 'Extender el permiso 24 horas desde ahora',
    'permitir_ayuda' => 'El equipo de AlojaFlow podrá entrar como administrador, sin cambiar los módulos ni a los administradores, hasta dentro de 24 horas. Puedes retirar el permiso cuando quieras.',
    'permitido' => 'Listo: el soporte puede entrar hasta :hasta.',
    'retirar' => 'Retirar el permiso',
    'retirar_ayuda' => 'Quien del soporte esté dentro sale en ese momento y ya no podrá volver a entrar.',
    'retirado' => 'Permiso retirado.',
    'sin_permiso_estado' => 'El soporte no tiene permiso para entrar.',
    'permitido_estado' => 'El soporte puede entrar hasta :hasta.',
    'accesos' => 'Entradas del soporte (últimos 90 días)',
    'sin_accesos' => 'El soporte no ha entrado.',
    'quien' => 'Quién',
    'cuando' => 'Cuándo',
    'hasta' => 'Hasta',
    'motivo' => 'Motivo',
    'dentro' => 'Dentro ahora',
    'con_permiso' => 'Con tu permiso',
    'emergencia' => 'Emergencia: :motivo',
    'usuario' => 'Soporte AlojaFlow — :nombre',
    'solo_instancia' => 'El acceso de soporte solo existe en el servicio alojado de AlojaFlow.',
    'sin_permiso' => 'El cliente no ha dado permiso al soporte. Sin permiso solo se entra en una emergencia, con el motivo (--emergencia).',
    'nombre_invalido' => 'Falta el nombre de quien atiende (hasta 80 caracteres).',
    'minutos_invalidos' => 'Los minutos van de 1 a :maximo.',
    'emergencia_asunto' => 'El soporte de AlojaFlow entró a tu sistema por una emergencia',
    'emergencia_cuerpo' => "Hola:\n\n:nombre, del soporte de AlojaFlow, entró a tu sistema sin tu permiso por una emergencia.\n\nMotivo: :motivo\n\nSu acceso termina el :hasta. Todo lo que haga queda en la auditoría (Sistema → Auditoría). Si tienes dudas, responde a este correo o escríbenos a soporte.",
    'consola' => [
        'listo' => 'Acceso listo para :usuario hasta :hasta. El enlace sirve una vez y caduca en 5 minutos.',
    ],
];
