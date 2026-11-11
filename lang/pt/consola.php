<?php

/*
 * AlojaFlow — sistema de gestión de alojamientos
 * Copyright (C) 2026 Juan Galeana Durán
 * SPDX-License-Identifier: FSL-1.1-ALv2
 */

declare(strict_types=1);

return [
    'calendarios_leidos' => 'Calendários lidos: :cuantos',
    'mensajes' => 'Eventos: :eventos · Por data: :generados · Enviados: :enviados',
    'administrador_listo' => 'Administrador pronto: :correo',
    'tareas_nuevas' => 'Tarefas novas: :cuantas',
    'ejecuciones_borradas' => 'Execuções de sincronização apagadas: :cuantas',
    'plazos' => 'Prazos de conservação: :detalle',
    'copias_borradas' => 'Cópias de atualizações apagadas: :cuantas',
    'vencidas_canceladas' => 'Reservas vencidas canceladas: :cuantas',
    'demo_no_activa' => 'Esta instalação não está em modo demo: nada é apagado.',
    'demo_reiniciada' => 'Demonstração reiniciada.',
    'paquete' => [
        'sin_cambios' => 'CAMBIOS.md não tem a secção «## :version».',
        'de' => 'Pacote do AlojaFlow :version',
        'no_pasa' => 'O pacote não passa a verificação: :errores',
        'instalador' => 'Instalador',
        'instalador_completo' => 'Instalador com o zip dentro',
        'archivos' => 'Ficheiros',
        'tamano' => 'Tamanho',
        'tamano_valor' => ':zip (:descomprimido depois de descomprimir)',
        'listo' => 'Pacote pronto e verificado.',
        'para_actualizar' => 'Para atualizar uma instalação: carregue-o em Sistema → :pantalla, ou na consola:',
        'para_instalar' => 'Para instalar de raiz: carregue :archivo para o alojamento e abra-o no navegador.',
    ],
    // Instalar sin navegador y enlace de entrada (D-162).
    'instalar' => [
        'ya_estaba' => 'O AlojaFlow já estava instalado aqui: nada foi alterado.',
        'idioma' => 'O idioma deve ser um de: :idiomas.',
        'lista' => 'AlojaFlow instalado. Super administrador: :correo.',
        'enlace' => 'Ligação de entrada de uso único (expira em 30 minutos):',
    ],
    'enlace_entrada' => [
        'sin_super' => 'Não há nenhum super administrador ativo com esse e-mail.',
        'dos_pasos' => 'Esse super administrador usa a verificação em dois passos: a ligação não lhe serve, entra com a sua palavra-passe.',
        'ya_tiene_contrasena' => 'Esse super administrador já criou a sua palavra-passe e entra com ela. A equipa do AlojaFlow entra como suporte, com a autorização do cliente.',
    ],
    'tareas_pendientes' => [
        'nada' => 'Nada pendente.',
        'tarea' => 'Tarefa',
        'estado' => 'Estado',
        'ventana' => 'A janela vai de 1 a 1440 minutos.',
    ],
];
